<?php

use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\BatalkanPengajuan;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Actions\Persetujuan\KembalikanPengajuan;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\TolakPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Models\RiwayatStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->k = siapkanAnggaran('100000000.00');
});

function setujuiPenuh(object $k, $p)
{
    $p = app(VerifikasiPengajuan::class)->handle($p, $k->kepalaPmat);
    $p = app(VerifikasiPengajuan::class)->handle($p, $k->keu);

    return app(SetujuiPengajuan::class)->handle($p, $k->wadek);
}

test('kembalikan tanpa catatan memadai → validasi gagal', function () {
    $p = buatPengajuanDiajukan($this->k);

    expect(fn () => app(KembalikanPengajuan::class)->handle($p, $this->k->kepalaPmat, ''))->toThrow(ValidationException::class)
        ->and(fn () => app(KembalikanPengajuan::class)->handle($p, $this->k->kepalaPmat, 'kurang'))->toThrow(ValidationException::class);
    expect($p->fresh()->status)->toBe(StatusPengajuan::Diajukan);
});

test('dikembalikan → admin-unit mengedit item, mengajukan ulang (nomor tetap), alur mulai dari tahap pertama', function () {
    $p = buatPengajuanDiajukan($this->k);
    $p = app(VerifikasiPengajuan::class)->handle($p, $this->k->kepalaPmat);
    $p = app(KembalikanPengajuan::class)->handle($p, $this->k->keu, 'Harga satuan konsumsi terlalu tinggi');

    expect($p->status)->toBe(StatusPengajuan::Dikembalikan)
        ->and($p->rabAwal()->first()->status)->toBe(StatusRab::Draf)
        ->and(RiwayatStatus::where('model_id', $p->id)->latest('id')->first()->tahap)->toBe(TahapPersetujuan::Keuangan);

    $p = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($this->k->tahun->id), [itemRab($this->k->akun->id, '2', '100000')], $this->k->adminPmat, $p);
    expect($p->total_diajukan)->toBe('200000.00');

    $p = app(AjukanPengajuan::class)->handle($p, $this->k->adminPmat);
    expect($p->nomor)->toBe('PGJ/2027/0001')
        ->and($p->tahapBerjalan())->toBe(TahapPersetujuan::KepalaUnit);

    // Kepala unit yang sama boleh memverifikasi lagi di tahap yang sama; alur berlanjut sampai disetujui.
    $p = setujuiPenuh($this->k, $p);
    expect($p->status)->toBe(StatusPengajuan::Disetujui)
        ->and($p->fresh()->total_disetujui)->toBe('200000.00');
});

test('ditolak bersifat final dan tidak bisa diajukan ulang', function () {
    $p = app(TolakPengajuan::class)->handle(buatPengajuanDiajukan($this->k), $this->k->kepalaPmat, 'Kegiatan tidak sesuai rencana unit');

    expect($p->status)->toBe(StatusPengajuan::Ditolak)
        ->and($p->rabAwal()->first()->status)->toBe(StatusRab::Ditolak)
        ->and($this->k->adminPmat->can('update', $p))->toBeFalse()
        ->and(fn () => app(AjukanPengajuan::class)->handle($p, $this->k->adminPmat))->toThrow(AuthorizationException::class);
});

test('kembalikan/tolak hanya oleh pemegang tahap berjalan', function () {
    $p = buatPengajuanDiajukan($this->k);

    expect(fn () => app(KembalikanPengajuan::class)->handle($p, $this->k->keu, 'Belum giliran tahap keuangan'))->toThrow(AuthorizationException::class)
        ->and($this->k->keu->can('kembalikan', $p))->toBeFalse()
        ->and($this->k->kepalaPmat->can('tolak', $p))->toBeTrue();
});

test('admin-keuangan membatalkan pengajuan disetujui → sisa pagu bertambah kembali', function () {
    $p = setujuiPenuh($this->k, buatPengajuanDiajukan($this->k, '1', '30000000'));
    $sisa = fn () => app(HitungSisaPagu::class)->handle($this->k->tahun->id, $this->k->pmat->id)['sisa'];

    expect($sisa())->toBe('70000000.00');

    app(BatalkanPengajuan::class)->handle($p, 'Kegiatan dibatalkan oleh pimpinan', $this->k->keu);

    expect($p->fresh()->status)->toBe(StatusPengajuan::Dibatalkan)
        ->and($sisa())->toBe('100000000.00')
        ->and(RiwayatStatus::where('model_id', $p->id)->latest('id')->first()->tahap)->toBe(TahapPersetujuan::Keuangan);
});

test('admin-unit tidak bisa membatalkan pengajuan disetujui; admin-keuangan tidak bisa membatalkan draf', function () {
    $disetujui = setujuiPenuh($this->k, buatPengajuanDiajukan($this->k));

    expect($this->k->adminPmat->can('batalkan', $disetujui))->toBeFalse()
        ->and(fn () => app(BatalkanPengajuan::class)->handle($disetujui, 'Ingin dibatalkan saja', $this->k->adminPmat))
        ->toThrow(AuthorizationException::class);

    $draf = buatPengajuanDraf($this->k);
    expect($this->k->keu->can('batalkan', $draf))->toBeFalse();
});
