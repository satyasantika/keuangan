<?php

use App\Actions\Master\TutupTahunAnggaran;
use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Actions\Periode\BukaKunciPeriode;
use App\Actions\Periode\KunciPeriode;
use App\Actions\Spj\CatatRealisasi;
use App\Enums\StatusPeriode;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\TahunAnggarans\Pages\EditTahunAnggaran;
use App\Filament\Resources\TahunAnggarans\RelationManagers\PeriodeRelationManager;
use App\Models\Pagu;
use App\Models\Periode;
use App\Models\RekapPeriode;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2027-03-10 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->p = buatPengajuanDisetujui($this->k, kegiatan: ['tanggal_mulai_kegiatan' => '2027-01-10', 'tanggal_selesai_kegiatan' => '2027-01-11']);
    [$cair, $bukti] = dataPencairan('300000', '2027-01-20');
    app(CatatPencairan::class)->handle($this->p, $cair, $bukti, $this->k->bpp);
});

function periodeBulan(object $k, int $bulan): Periode
{
    return Periode::where('tahun_anggaran_id', $k->tahun->id)->where('bulan', $bulan)->firstOrFail();
}

function kunciSampai(object $k, int $bulan): void
{
    for ($b = 1; $b <= $bulan; $b++) {
        app(KunciPeriode::class)->handle(periodeBulan($k, $b), $k->keu, true);
    }
}

test('buka Januari saat Februari terkunci → ditolak; buka Februari lalu Januari → sukses', function () {
    kunciSampai($this->k, 2);
    $alasan = 'Koreksi resmi atas salah input realisasi bulan lalu';

    expect(fn () => app(BukaKunciPeriode::class)->handle(periodeBulan($this->k, 1), $alasan, $this->k->wadek))
        ->toThrow(KesalahanDomain::class, 'Februari 2027');

    app(BukaKunciPeriode::class)->handle(periodeBulan($this->k, 2), $alasan, $this->k->wadek);
    $jan = app(BukaKunciPeriode::class)->handle(periodeBulan($this->k, 1), $alasan, $this->k->wadek);

    expect($jan->status)->toBe(StatusPeriode::Terbuka)
        ->and($jan->riwayatStatus()->latest('id')->first()->catatan)->toBe($alasan)
        ->and(RekapPeriode::where('periode_id', $jan->id)->exists())->toBeTrue();
});

test('alasan pendek ditolak; admin-keuangan tidak bisa membuka kunci', function () {
    kunciSampai($this->k, 1);

    expect(fn () => app(BukaKunciPeriode::class)->handle(periodeBulan($this->k, 1), 'terlalu pendek', $this->k->wadek))->toThrow(KesalahanDomain::class)
        ->and(fn () => app(BukaKunciPeriode::class)->handle(periodeBulan($this->k, 1), 'Koreksi resmi atas salah input realisasi', $this->k->keu))->toThrow(AuthorizationException::class);
});

test('setelah dibuka realisasi Januari dapat dicatat; kunci ulang → versi 2 dan snapshot diperbarui', function () {
    kunciSampai($this->k, 1);
    $rekapLama = RekapPeriode::where('periode_id', periodeBulan($this->k, 1)->id)->where('unit_id', $this->k->pmat->id)->first();
    expect($rekapLama->total_realisasi)->toBe('0.00');

    app(BukaKunciPeriode::class)->handle(periodeBulan($this->k, 1), 'Koreksi resmi: realisasi Januari belum tercatat', $this->k->wadek);

    [$data, $tautan] = dataRealisasi($this->p->fresh(), '1', '200000', tanggal: '2027-01-15');
    app(CatatRealisasi::class)->handle($this->p->fresh(), $data, $tautan, $this->k->adminPmat);

    $jan = app(KunciPeriode::class)->handle(periodeBulan($this->k, 1), $this->k->keu, true);
    $rekap = RekapPeriode::where('periode_id', $jan->id)->where('unit_id', $this->k->pmat->id)->first();

    expect($jan->versi_kunci)->toBe(2)
        ->and($rekap->versi_kunci)->toBe(2)
        ->and($rekap->total_realisasi)->toBe('200000.00')
        ->and(RekapPeriode::where('periode_id', $jan->id)->count())->toBe(1);
});

test('tahun tidak bisa ditutup bila ada periode terbuka; admin-keuangan tidak berhak menutup', function () {
    expect(fn () => app(TutupTahunAnggaran::class)->handle($this->k->tahun, $this->k->wadek, true))->toThrow(KesalahanDomain::class, '12 periode')
        ->and(fn () => app(TutupTahunAnggaran::class)->handle($this->k->tahun, $this->k->keu, true))->toThrow(AuthorizationException::class);
});

test('penutupan tahun memerlukan konfirmasi bila ada pengajuan dicairkan; setelah ditutup pengajuan & pagu baru ditolak', function () {
    $this->travelTo('2028-01-05 10:00:00');
    kunciSampai($this->k, 12);

    expect(fn () => app(TutupTahunAnggaran::class)->handle($this->k->tahun, $this->k->wadek))->toThrow(KesalahanDomain::class, 'dicairkan');

    $tahun = app(TutupTahunAnggaran::class)->handle($this->k->tahun, $this->k->wadek, konfirmasi: true);
    expect($tahun->status)->toBe(StatusTahunAnggaran::Ditutup);

    expect(fn () => app(SimpanDrafPengajuan::class)->handle(dataKegiatan($tahun->id), [itemRab($this->k->akun->id)], $this->k->adminPmat))
        ->toThrow(KesalahanDomain::class, 'ditutup')
        ->and(fn () => Pagu::factory()->create(['tahun_anggaran_id' => $tahun->id, 'unit_id' => $this->k->pbio->id, 'dibuat_oleh' => $this->k->keu->id]))
        ->toThrow(KesalahanDomain::class, 'ditutup');
});

test('panel: admin-keuangan mengunci dan wadek membuka kunci lewat relation manager', function () {
    $jan = periodeBulan($this->k, 1);

    Livewire::actingAs($this->k->keu)
        ->test(PeriodeRelationManager::class, ['ownerRecord' => $this->k->tahun, 'pageClass' => EditTahunAnggaran::class])
        ->assertTableActionHidden('bukaKunci', $jan)
        ->callTableAction('kunci', $jan, data: ['paham' => true])
        ->assertNotified('Januari 2027 dikunci');

    expect($jan->fresh()->status)->toBe(StatusPeriode::Terkunci);

    Livewire::actingAs($this->k->wadek)
        ->test(PeriodeRelationManager::class, ['ownerRecord' => $this->k->tahun, 'pageClass' => EditTahunAnggaran::class])
        ->assertTableActionHidden('kunci', $jan->fresh())
        ->callTableAction('bukaKunci', $jan->fresh(), data: ['alasan' => 'Koreksi resmi dari bagian keuangan universitas'])
        ->assertNotified('Januari 2027 dibuka kembali');

    expect($jan->fresh()->status)->toBe(StatusPeriode::Terbuka);
});
