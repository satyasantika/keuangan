<?php

use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\KembalikanSpj;
use App\Actions\Spj\TerimaSpj;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusSpj;
use App\Enums\TahapPersetujuan;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Models\RiwayatStatus;
use App\Models\Spj;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
});

test('ajukan tanpa realisasi → gagal', function () {
    $p = buatPengajuanDicairkan($this->k);
    $spj = Spj::create(['pengajuan_id' => $p->id, 'dibuat_oleh' => $this->k->adminPmat->id]);

    expect(fn () => app(AjukanSpj::class)->handle($spj, $this->k->adminPmat))->toThrow(KesalahanDomain::class, 'minimal satu realisasi');
});

test('nomor SPJ/2027/0001 terbit dan riwayat tercatat', function () {
    $spj = app(AjukanSpj::class)->handle(buatSpjDenganRealisasi($this->k), $this->k->adminPmat);

    expect($spj->status)->toBe(StatusSpj::Diajukan)
        ->and($spj->nomor)->toBe('SPJ/2027/0001')
        ->and($spj->diajukan_pada)->not->toBeNull()
        ->and(RiwayatStatus::where('model_type', 'spj')->where('model_id', $spj->id)->value('ke'))->toBe('diajukan');
});

test('pengaju SPJ yang juga bpp tidak bisa menerima SPJ-nya sendiri', function () {
    $this->k->adminPmat->assignRole(Peran::Bpp->value);
    $spj = app(AjukanSpj::class)->handle(buatSpjDenganRealisasi($this->k), $this->k->adminPmat);

    expect(fn () => app(TerimaSpj::class)->handle($spj, $this->k->adminPmat))->toThrow(PelanggaranPemisahanTugasException::class);
    expect($this->k->adminPmat->can('verifikasi', $spj))->toBeFalse()
        ->and($this->k->bpp->can('verifikasi', $spj))->toBeTrue();
});

test('dikembalikan → admin-unit menambah realisasi lalu mengajukan ulang (nomor tetap)', function () {
    $spj = app(AjukanSpj::class)->handle(buatSpjDenganRealisasi($this->k, '200000'), $this->k->adminPmat);

    expect(fn () => app(KembalikanSpj::class)->handle($spj, $this->k->keu, 'kurang'))->toThrow(ValidationException::class);

    $spj = app(KembalikanSpj::class)->handle($spj, $this->k->keu, 'Lengkapi kuitansi kedua');
    expect($spj->status)->toBe(StatusSpj::Dikembalikan)
        ->and(RiwayatStatus::where('model_id', $spj->id)->latest('id')->first()->tahap)->toBe(TahapPersetujuan::Keuangan);

    $p = $spj->pengajuan;
    [$data, $tautan] = dataRealisasi($p, '1', '100000');
    app(CatatRealisasi::class)->handle($p, $data, $tautan, $this->k->adminPmat);

    $spj = app(AjukanSpj::class)->handle($spj->fresh(), $this->k->adminPmat);
    expect($spj->nomor)->toBe('SPJ/2027/0001')
        ->and(RiwayatStatus::where('model_id', $spj->id)->latest('id')->value('catatan'))->toBe('Diajukan ulang');
});

test('diterima dengan sisa dana 0 → pengajuan selesai', function () {
    $spj = app(AjukanSpj::class)->handle(buatSpjDenganRealisasi($this->k, '300000'), $this->k->adminPmat);
    $spj = app(TerimaSpj::class)->handle($spj, $this->k->bpp, 'Lengkap');

    $p = $spj->pengajuan->fresh();
    expect($spj->status)->toBe(StatusSpj::Diterima)
        ->and($spj->diputuskan_oleh)->toBe($this->k->bpp->id)
        ->and(RiwayatStatus::where('model_id', $spj->id)->latest('id')->first()->tahap)->toBe(TahapPersetujuan::Bendahara)
        ->and($p->status)->toBe(StatusPengajuan::Selesai)
        ->and($p->selesai_pada)->not->toBeNull()
        ->and($p->spjSudahDiterima())->toBeTrue();
});

test('diterima dengan sisa dana > 0 → pengajuan tetap dicairkan; realisasi terkunci', function () {
    $spj = app(AjukanSpj::class)->handle(buatSpjDenganRealisasi($this->k, '250000'), $this->k->adminPmat);
    $spj = app(TerimaSpj::class)->handle($spj, $this->k->keu);

    expect($spj->pengajuan->fresh()->status)->toBe(StatusPengajuan::Dicairkan)
        ->and($spj->pengajuan->fresh()->sisaDana())->toBe('50000.00');

    $r = $spj->realisasiItem()->first();
    expect(fn () => $r->update(['uraian' => 'Ubah']))->toThrow(KesalahanDomain::class)
        ->and($this->k->adminPmat->can('update', $r))->toBeFalse();
});

test('admin-unit tidak bisa memverifikasi; verifikator tidak bisa mengajukan', function () {
    $spj = buatSpjDenganRealisasi($this->k);

    expect($this->k->keu->can('ajukan', $spj))->toBeFalse();

    $spj = app(AjukanSpj::class)->handle($spj, $this->k->adminPmat);
    expect($this->k->adminPmat->can('verifikasi', $spj))->toBeFalse()
        ->and(fn () => app(TerimaSpj::class)->handle($spj, penggunaDenganPeran(Peran::Pimpinan)))->toThrow(AuthorizationException::class);
});
