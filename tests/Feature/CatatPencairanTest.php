<?php

use App\Actions\Pencairan\CatatPencairan;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPeriode;
use App\Enums\TahapPersetujuan;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\PeriodeTerkunciException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pencairan;
use App\Models\Periode;
use App\Models\RiwayatStatus;
use App\Support\Pengaturan;
use App\Support\VersiCacheDasbor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->catat = app(CatatPencairan::class);
});

test('pencairan pertama → pengajuan dicairkan, batas_spj dari tanggal pencairan bila kegiatan selesai lebih dulu', function () {
    $p = buatPengajuanDisetujui($this->k); // selesai 2027-03-11, total 300.000
    [$data, $tautan] = dataPencairan('300000', '2027-06-15');
    $versi = VersiCacheDasbor::versi();

    $c = $this->catat->handle($p, $data, $tautan, $this->k->bpp);
    $p->refresh();

    expect($c->status)->toBe(StatusPencairan::Dicatat)
        ->and($c->periode->bulan)->toBe(6)
        ->and($c->tautanJenis(JenisTautan::BuktiPencairan)->count())->toBe(1)
        ->and($p->status)->toBe(StatusPengajuan::Dicairkan)
        ->and($p->batas_spj->toDateString())->toBe('2027-06-29')
        ->and($p->total_dicairkan)->toBe('300000.00')
        ->and($p->punyaPencairanAktif())->toBeTrue()
        ->and(VersiCacheDasbor::versi())->toBeGreaterThan($versi)
        ->and(RiwayatStatus::where('model_type', 'pencairan')->where('model_id', $c->id)->first()->tahap)->toBe(TahapPersetujuan::Bendahara);
});

test('batas_spj dari tanggal selesai kegiatan bila kegiatan selesai setelah pencairan; tidak berubah bila pengaturan diubah', function () {
    $p = buatPengajuanDisetujui($this->k, kegiatan: ['tanggal_mulai_kegiatan' => '2027-07-08', 'tanggal_selesai_kegiatan' => '2027-07-10']);
    [$data, $tautan] = dataPencairan('100000', '2027-06-15');

    $this->catat->handle($p, $data, $tautan, $this->k->bpp);
    expect($p->fresh()->batas_spj->toDateString())->toBe('2027-07-24');

    Pengaturan::set('batas_hari_spj', 30, $this->k->keu);
    [$data2, $tautan2] = dataPencairan('100000', '2027-06-16');
    $this->catat->handle($p->fresh(), $data2, $tautan2, $this->k->bpp);

    expect($p->fresh()->batas_spj->toDateString())->toBe('2027-07-24')
        ->and($p->fresh()->total_dicairkan)->toBe('200000.00');
});

test('Σ pencairan melebihi total RAB → ditolak', function () {
    $p = buatPengajuanDisetujui($this->k);
    [$data, $tautan] = dataPencairan('200000');
    $this->catat->handle($p, $data, $tautan, $this->k->bpp);

    [$lebih, $tautan2] = dataPencairan('100000.01');
    expect(fn () => $this->catat->handle($p->fresh(), $lebih, $tautan2, $this->k->bpp))
        ->toThrow(KesalahanDomain::class, 'melebihi total RAB');
    expect(Pencairan::count())->toBe(1);
});

test('pengajuan berstatus diajukan → ditolak', function () {
    $p = buatPengajuanDiajukan($this->k);
    [$data, $tautan] = dataPencairan('100000');

    expect(fn () => $this->catat->handle($p, $data, $tautan, $this->k->bpp))->toThrow(TransisiStatusTidakSahException::class);
});

test('tanggal di periode terkunci → PeriodeTerkunciException', function () {
    $p = buatPengajuanDisetujui($this->k);
    Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 5)->first()->forceFill(['status' => StatusPeriode::Terkunci])->save();
    [$data, $tautan] = dataPencairan('100000', '2027-05-20');

    expect(fn () => $this->catat->handle($p, $data, $tautan, $this->k->bpp))->toThrow(PeriodeTerkunciException::class);
    expect(Pencairan::count())->toBe(0)
        ->and($p->fresh()->status)->toBe(StatusPengajuan::Disetujui);
});

test('tanpa tautan bukti → validasi gagal; tautan tanpa konfirmasi akses terbatas → gagal', function () {
    $p = buatPengajuanDisetujui($this->k);
    [$data] = dataPencairan('100000');

    expect(fn () => $this->catat->handle($p, $data, [], $this->k->bpp))->toThrow(ValidationException::class);

    [$data2, $tautan] = dataPencairan('100000', konfirmasi: false);
    expect(fn () => $this->catat->handle($p, $data2, $tautan, $this->k->bpp))->toThrow(ValidationException::class);
    expect(Pencairan::count())->toBe(0);
});

test('tanggal di masa depan ditolak', function () {
    $p = buatPengajuanDisetujui($this->k);
    [$data, $tautan] = dataPencairan('100000', '2027-06-21');

    expect(fn () => $this->catat->handle($p, $data, $tautan, $this->k->bpp))->toThrow(ValidationException::class);
});

test('wadek penyetuju yang juga berperan bpp → ditolak', function () {
    $this->k->wadek->assignRole(Peran::Bpp->value);
    $p = buatPengajuanDisetujui($this->k);
    [$data, $tautan] = dataPencairan('100000');

    expect(fn () => $this->catat->handle($p, $data, $tautan, $this->k->wadek))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('admin-unit → 403', function () {
    $p = buatPengajuanDisetujui($this->k);
    [$data, $tautan] = dataPencairan('100000');

    expect(fn () => $this->catat->handle($p, $data, $tautan, $this->k->adminPmat))->toThrow(AuthorizationException::class);
});
