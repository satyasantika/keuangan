<?php

use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Models\Akun;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use Illuminate\Support\Facades\Schema;

test('tabel, indeks, dan relasi pengajuan-rab-item berfungsi', function () {
    buatTahun(2027);
    $pengajuan = Pengajuan::factory()->create();
    $rab = Rab::factory()->create(['pengajuan_id' => $pengajuan->id, 'dibuat_oleh' => $pengajuan->dibuat_oleh]);
    $pengajuan->forceFill(['rab_aktif_id' => $rab->id])->save();
    $item = RabItem::factory()->create(['rab_id' => $rab->id]);
    RabItem::factory()->create(['rab_id' => $rab->id, 'urutan' => 2, 'asal_rab_item_id' => $item->id]);

    $pengajuan = $pengajuan->fresh();

    expect($pengajuan->rabAktif->is($rab))->toBeTrue()
        ->and($pengajuan->rabAwal->is($rab))->toBeTrue()
        ->and($pengajuan->rab)->toHaveCount(1)
        ->and($rab->item)->toHaveCount(2)
        ->and($rab->item()->with('asal')->get()[1]->asal->is($item))->toBeTrue()
        ->and($item->akun)->toBeInstanceOf(Akun::class)
        ->and($pengajuan->tahunAnggaran->tahun)->toBe(2027)
        ->and($item->fresh()->volume)->toBe('2.00')
        ->and($pengajuan->total_diajukan)->toBe('0.00')
        ->and($pengajuan->getMorphClass())->toBe('pengajuan');

    $indeks = collect(Schema::getIndexes('pengajuan'))->pluck('columns')->map(fn ($k) => implode(',', $k));
    expect($indeks)->toContain('tahun_anggaran_id,unit_id,status')
        ->toContain('batas_spj')
        ->toContain('judul_kegiatan');

    expect(collect(Schema::getIndexes('rab'))->firstWhere('unique', true) !== null)->toBeTrue();
});

test('semua transisi pada diagram pengajuan diizinkan', function (StatusPengajuan $dari, StatusPengajuan $ke) {
    expect($dari->bolehBerpindahKe($ke))->toBeTrue();
})->with([
    [StatusPengajuan::Draf, StatusPengajuan::Diajukan],
    [StatusPengajuan::Draf, StatusPengajuan::Dibatalkan],
    [StatusPengajuan::Diajukan, StatusPengajuan::DiverifikasiUnit],
    [StatusPengajuan::Diajukan, StatusPengajuan::DiverifikasiKeuangan],
    [StatusPengajuan::Diajukan, StatusPengajuan::Disetujui],
    [StatusPengajuan::DiverifikasiUnit, StatusPengajuan::DiverifikasiKeuangan],
    [StatusPengajuan::DiverifikasiUnit, StatusPengajuan::Disetujui],
    [StatusPengajuan::DiverifikasiKeuangan, StatusPengajuan::Disetujui],
    [StatusPengajuan::Diajukan, StatusPengajuan::Dikembalikan],
    [StatusPengajuan::DiverifikasiUnit, StatusPengajuan::Dikembalikan],
    [StatusPengajuan::DiverifikasiKeuangan, StatusPengajuan::Dikembalikan],
    [StatusPengajuan::Dikembalikan, StatusPengajuan::Diajukan],
    [StatusPengajuan::Dikembalikan, StatusPengajuan::Dibatalkan],
    [StatusPengajuan::Diajukan, StatusPengajuan::Ditolak],
    [StatusPengajuan::DiverifikasiUnit, StatusPengajuan::Ditolak],
    [StatusPengajuan::DiverifikasiKeuangan, StatusPengajuan::Ditolak],
    [StatusPengajuan::Diajukan, StatusPengajuan::Dibatalkan],
    [StatusPengajuan::Disetujui, StatusPengajuan::Dibatalkan],
    [StatusPengajuan::Disetujui, StatusPengajuan::Dicairkan],
    [StatusPengajuan::Dicairkan, StatusPengajuan::Disetujui],
    [StatusPengajuan::Dicairkan, StatusPengajuan::Selesai],
]);

test('transisi pengajuan tidak sah ditolak', function (StatusPengajuan $dari, StatusPengajuan $ke) {
    expect($dari->bolehBerpindahKe($ke))->toBeFalse();
})->with([
    [StatusPengajuan::Draf, StatusPengajuan::Disetujui],
    [StatusPengajuan::Selesai, StatusPengajuan::Draf],
    [StatusPengajuan::Ditolak, StatusPengajuan::Diajukan],
    [StatusPengajuan::Dibatalkan, StatusPengajuan::Draf],
    [StatusPengajuan::Disetujui, StatusPengajuan::Diajukan],
    [StatusPengajuan::Dicairkan, StatusPengajuan::Dibatalkan],
]);

test('transisi RAB mengikuti diagram', function () {
    expect(StatusRab::Draf->bolehBerpindahKe(StatusRab::Diajukan))->toBeTrue()
        ->and(StatusRab::Diajukan->bolehBerpindahKe(StatusRab::DiverifikasiKeuangan))->toBeTrue()
        ->and(StatusRab::DiverifikasiKeuangan->bolehBerpindahKe(StatusRab::Disetujui))->toBeTrue()
        ->and(StatusRab::Diajukan->bolehBerpindahKe(StatusRab::Draf))->toBeTrue()
        ->and(StatusRab::DiverifikasiKeuangan->bolehBerpindahKe(StatusRab::Ditolak))->toBeTrue()
        ->and(StatusRab::Disetujui->bolehBerpindahKe(StatusRab::Digantikan))->toBeTrue()
        ->and(StatusRab::Draf->bolehBerpindahKe(StatusRab::Disetujui))->toBeFalse()
        ->and(StatusRab::Digantikan->bolehBerpindahKe(StatusRab::Disetujui))->toBeFalse()
        ->and(StatusRab::Ditolak->bolehBerpindahKe(StatusRab::Diajukan))->toBeFalse();
});

test('dapatDiubah hanya draf dan dikembalikan; komitmen sesuai BR-04', function () {
    $dapat = collect(StatusPengajuan::cases())->filter->dapatDiubah()->values()->all();

    expect($dapat)->toBe([StatusPengajuan::Draf, StatusPengajuan::Dikembalikan])
        ->and(StatusPengajuan::DiverifikasiUnit->menghitungKomitmen())->toBe('total_diajukan')
        ->and(StatusPengajuan::Dicairkan->menghitungKomitmen())->toBe('total_disetujui')
        ->and(StatusPengajuan::Selesai->menghitungKomitmen())->toBe('total_realisasi')
        ->and(StatusPengajuan::Dikembalikan->menghitungKomitmen())->toBeNull()
        ->and(StatusPengajuan::Ditolak->menghitungKomitmen())->toBeNull();
});

test('akun yang dipakai item RAB tidak bisa dihapus', function () {
    buatTahun(2027);
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);
    $dipakai = Akun::factory()->create();
    $bebas = Akun::factory()->create();
    RabItem::factory()->create(['akun_id' => $dipakai->id]);

    expect($dipakai->sedangDipakai())->toBeTrue()
        ->and($keu->can('delete', $dipakai))->toBeFalse()
        ->and($keu->can('delete', $bebas))->toBeTrue();
});
