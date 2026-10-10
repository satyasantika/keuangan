<?php

use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Enums\StatusPengembalian;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use App\Models\JenisPencairan;
use App\Models\Pencairan;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\PotonganPajak;
use App\Models\RealisasiItem;
use App\Models\RiwayatStatus;
use App\Models\Spj;
use App\Models\TautanBerkas;
use App\Models\TautanBerkasBeku;

beforeEach(function () {
    buatTahun(2027);
    $this->pengajuan = Pengajuan::factory()->create(['total_dicairkan' => '1000000.00']);
    $jenis = JenisPencairan::create(['kode' => 'LS', 'nama' => 'Pembayaran Langsung']);
    $pencairan = new Pencairan(['pengajuan_id' => $this->pengajuan->id, 'jenis_pencairan_id' => $jenis->id, 'tanggal' => '2027-06-01', 'jumlah' => '1000000.00', 'nomor_bukti' => 'TRF']);
    $pencairan->forceFill(['dicatat_oleh' => $this->pengajuan->dibuat_oleh])->save();
    $this->spj = Spj::factory()->create(['pengajuan_id' => $this->pengajuan->id]);
});

test('relasi spj, realisasi, potongan pajak, pengembalian', function () {
    $r = RealisasiItem::factory()->create(['spj_id' => $this->spj->id]);
    PotonganPajak::factory()->create(['realisasi_item_id' => $r->id]);
    Pengembalian::factory()->create(['pengajuan_id' => $this->pengajuan->id]);

    expect($this->pengajuan->spj->is($this->spj))->toBeTrue()
        ->and($this->spj->realisasiItem)->toHaveCount(1)
        ->and($r->potonganPajak)->toHaveCount(1)
        ->and($r->periode->bulan)->toBe(6)
        ->and($this->pengajuan->pengembalian)->toHaveCount(1)
        ->and($this->pengajuan->realisasiItem()->count())->toBe(1)
        ->and($this->pengajuan->punyaRealisasi())->toBeTrue()
        ->and($this->pengajuan->spjSudahDiterima())->toBeFalse();
});

test('guard menolak perubahan realisasi saat SPJ diajukan atau diterima', function (StatusSpj $status) {
    $r = RealisasiItem::factory()->create(['spj_id' => $this->spj->id]);
    $p = PotonganPajak::factory()->create(['realisasi_item_id' => $r->id]);
    $this->spj->forceFill(['status' => $status])->save();

    expect(fn () => $r->fresh()->update(['uraian' => 'ubah']))->toThrow(KesalahanDomain::class)
        ->and(fn () => $r->fresh()->delete())->toThrow(KesalahanDomain::class)
        ->and(fn () => RealisasiItem::factory()->create(['spj_id' => $this->spj->id]))->toThrow(KesalahanDomain::class);

    if ($status === StatusSpj::Diterima) {
        expect(fn () => $p->fresh()->update(['nomor_bukti_setor' => 'X']))->toThrow(KesalahanDomain::class);
    } else {
        $p->fresh()->update(['nomor_bukti_setor' => 'NTPN-1']);
        expect($p->fresh()->nomor_bukti_setor)->toBe('NTPN-1');
    }
})->with([StatusSpj::Diajukan, StatusSpj::Diterima]);

test('sisaDana lebih bayar dan kurang bayar; hitung ulang mengisi total baru', function () {
    RealisasiItem::factory()->create(['spj_id' => $this->spj->id, 'jumlah_bruto' => '750000.00']);
    $r = RealisasiItem::factory()->create(['spj_id' => $this->spj->id, 'jumlah_bruto' => '100000.00']);
    PotonganPajak::factory()->create(['realisasi_item_id' => $r->id, 'nilai' => '2500.00']);
    Pengembalian::factory()->status(StatusPengembalian::Diverifikasi)->create(['pengajuan_id' => $this->pengajuan->id, 'jumlah' => '150000.00']);
    Pengembalian::factory()->create(['pengajuan_id' => $this->pengajuan->id, 'jumlah' => '99.00']);

    app(HitungUlangTotalPengajuan::class)->handle($this->pengajuan);
    $p = $this->pengajuan->fresh();

    expect($p->total_realisasi)->toBe('850000.00')
        ->and($p->total_pajak)->toBe('2500.00')
        ->and($p->total_dikembalikan)->toBe('150000.00')
        ->and($p->sisaDana())->toBe('150000.00')
        ->and($this->spj->fresh()->total_realisasi)->toBe('850000.00')
        ->and($this->spj->fresh()->total_pajak)->toBe('2500.00');

    RealisasiItem::factory()->create(['spj_id' => $this->spj->id, 'jumlah_bruto' => '200000.00']);
    app(HitungUlangTotalPengajuan::class)->handle($p);
    expect($p->fresh()->sisaDana())->toBe('-50000.00');
});

test('rekonsiliasi mendeteksi selisih buatan pada total realisasi dan SPJ', function () {
    RealisasiItem::factory()->create(['spj_id' => $this->spj->id, 'jumlah_bruto' => '750000.00']);
    app(HitungUlangTotalPengajuan::class)->handle($this->pengajuan);
    $this->artisan('keuangan:rekonsiliasi')->assertSuccessful();

    $this->pengajuan->fresh()->forceFill(['total_realisasi' => '1.00'])->saveQuietly();
    $this->spj->fresh()->forceFill(['total_pajak' => '9.00'])->saveQuietly();

    $this->artisan('keuangan:rekonsiliasi')->expectsOutputToContain('total_realisasi')->assertFailed();
    $this->artisan('keuangan:rekonsiliasi --perbaiki')->assertSuccessful();
    $this->artisan('keuangan:rekonsiliasi')->assertSuccessful();
});

test('TautanBerkasBeku tidak dapat diubah atau dihapus', function () {
    $tautan = TautanBerkas::query()->forceCreate([
        'pemilik_type' => 'pengajuan', 'pemilik_id' => $this->pengajuan->id, 'jenis' => 'tor', 'label' => 'TOR',
        'url' => 'https://drive.google.com/file/d/1X/view', 'penyedia' => 'google_drive', 'ditambahkan_oleh' => $this->pengajuan->dibuat_oleh,
    ]);
    $riwayat = RiwayatStatus::query()->forceCreate(['model_type' => 'spj', 'model_id' => $this->spj->id, 'ke' => 'diterima']);
    $beku = TautanBerkasBeku::create([
        'spj_id' => $this->spj->id, 'riwayat_status_id' => $riwayat->id, 'tautan_berkas_id' => $tautan->id,
        'pemilik_type' => 'pengajuan', 'pemilik_id' => $this->pengajuan->id, 'jenis' => 'tor', 'label' => 'TOR',
        'url' => $tautan->url, 'status_cek' => 'belum', 'dibekukan_pada' => now(),
    ]);

    expect(fn () => $beku->update(['url' => 'https://drive.google.com/file/d/1Y/view']))->toThrow(KesalahanDomain::class)
        ->and(fn () => $beku->delete())->toThrow(KesalahanDomain::class)
        ->and($this->spj->tautanBeku)->toHaveCount(1);
});
