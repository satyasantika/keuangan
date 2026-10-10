<?php

use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Exceptions\AksiSedangDiprosesException;
use App\Exceptions\PaguTidakCukupException;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\RiwayatStatus;
use App\Support\Pengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->k = siapkanAnggaran('100000000.00');
    $this->verifikasi = app(VerifikasiPengajuan::class);
    $this->setujui = app(SetujuiPengajuan::class);
});

test('alur penuh 3 tahap oleh 3 pengguna → disetujui dengan 4 baris riwayat bertahap', function () {
    $p = buatPengajuanDiajukan($this->k);

    $p = $this->verifikasi->handle($p, $this->k->kepalaPmat);
    expect($p->status)->toBe(StatusPengajuan::DiverifikasiUnit);

    $p = $this->verifikasi->handle($p, $this->k->keu);
    expect($p->status)->toBe(StatusPengajuan::DiverifikasiKeuangan);

    $p = $this->setujui->handle($p, $this->k->wadek, 'Disetujui');
    $p->refresh();

    expect($p->status)->toBe(StatusPengajuan::Disetujui)
        ->and($p->total_disetujui)->toBe('300000.00')
        ->and($p->disetujui_pada)->not->toBeNull()
        ->and($p->rabAwal->status)->toBe(StatusRab::Disetujui)
        ->and($p->rabAwal->disetujui_oleh)->toBe($this->k->wadek->id);

    $riwayat = RiwayatStatus::where('model_type', 'pengajuan')->where('model_id', $p->id)->orderBy('id')->get();
    expect($riwayat)->toHaveCount(4)
        ->and($riwayat->pluck('ke')->all())->toBe(['diajukan', 'diverifikasi_unit', 'diverifikasi_keuangan', 'disetujui'])
        ->and($riwayat->pluck('tahap')->map(fn ($t) => $t?->value)->all())->toBe([null, 'kepala_unit', 'keuangan', 'wadek']);
});

test('kepala-unit unit lain ditolak', function () {
    $kepalaPbio = penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $this->k->pbio->id]);

    expect(fn () => $this->verifikasi->handle(buatPengajuanDiajukan($this->k), $kepalaPbio))->toThrow(AuthorizationException::class);
});

test('pembuat pengajuan yang juga kepala-unit ditolak', function () {
    $ganda = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pmat->id]);
    $ganda->assignRole(Peran::KepalaUnit->value);
    $p = app(AjukanPengajuan::class)->handle(buatPengajuanDraf($this->k, oleh: $ganda), $ganda);

    expect(fn () => $this->verifikasi->handle($p, $ganda))->toThrow(PelanggaranPemisahanTugasException::class);
    expect($ganda->can('verifikasi', $p))->toBeFalse();
});

test('pengguna dua peran yang sudah verifikasi tahap unit tidak boleh tahap keuangan', function () {
    $ganda = penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $this->k->pmat->id]);
    $ganda->assignRole(Peran::AdminKeuangan->value);
    $p = $this->verifikasi->handle(buatPengajuanDiajukan($this->k), $ganda);

    expect($ganda->can('verifikasi', $p))->toBeFalse()
        ->and(fn () => $this->verifikasi->handle($p, $ganda))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('wajib_kepala_unit false → admin-keuangan langsung dari diajukan', function () {
    Pengaturan::set('wajib_verifikasi_kepala_unit', false, $this->k->keu);
    $p = buatPengajuanDiajukan($this->k);

    expect($p->tahapBerjalan())->toBe(TahapPersetujuan::Keuangan)
        ->and(fn () => $this->verifikasi->handle($p, $this->k->kepalaPmat))->toThrow(AuthorizationException::class);

    expect($this->verifikasi->handle($p, $this->k->keu)->status)->toBe(StatusPengajuan::DiverifikasiKeuangan);
});

test('wajib_wadek false → verifikasi admin-keuangan menghasilkan disetujui', function () {
    Pengaturan::set('wajib_persetujuan_wadek', false, $this->k->keu);
    $p = $this->verifikasi->handle(buatPengajuanDiajukan($this->k), $this->k->kepalaPmat);

    $p = $this->verifikasi->handle($p, $this->k->keu);

    expect($p->status)->toBe(StatusPengajuan::Disetujui)
        ->and($p->fresh()->total_disetujui)->toBe('300000.00')
        ->and(RiwayatStatus::where('model_id', $p->id)->latest('id')->first()->tahap)->toBe(TahapPersetujuan::Keuangan);
});

test('snapshot: mengubah pengaturan setelah diajukan tidak mengubah jalur pengajuan itu', function () {
    $p = buatPengajuanDiajukan($this->k);
    Pengaturan::set('wajib_verifikasi_kepala_unit', false, $this->k->keu);
    Pengaturan::set('wajib_persetujuan_wadek', false, $this->k->keu);

    expect($p->fresh()->tahapBerjalan())->toBe(TahapPersetujuan::KepalaUnit);

    $p = $this->verifikasi->handle($p, $this->k->kepalaPmat);
    $p = $this->verifikasi->handle($p, $this->k->keu);

    expect($p->status)->toBe(StatusPengajuan::DiverifikasiKeuangan);
});

test('sisa pagu habis oleh pengajuan lain sebelum persetujuan → ditolak', function () {
    $p = buatPengajuanDiajukan($this->k, '1', '60000000');
    $p = $this->verifikasi->handle($p, $this->k->kepalaPmat);
    $p = $this->verifikasi->handle($p, $this->k->keu);

    // Pagu diturunkan langsung (simulasi kondisi berubah) sehingga sisa tidak cukup.
    $this->k->pagu->forceFill(['jumlah' => '50000000.00'])->saveQuietly();

    expect(fn () => $this->setujui->handle($p, $this->k->wadek))->toThrow(PaguTidakCukupException::class);
    expect($p->fresh()->status)->toBe(StatusPengajuan::DiverifikasiKeuangan);
});

test('aksi kedua saat lock dipegang → AksiSedangDiprosesException', function () {
    $p = buatPengajuanDiajukan($this->k);
    $lock = Cache::store(config('keuangan.lock_store'))->lock("lock:aksi:pengajuan:{$p->id}", 15);
    $lock->get();

    expect(fn () => $this->verifikasi->handle($p, $this->k->kepalaPmat))->toThrow(AksiSedangDiprosesException::class);
    $lock->release();
});

test('status sudah berubah → TransisiStatusTidakSahException', function () {
    $p = buatPengajuanDiajukan($this->k);
    $basi = Pengajuan::find($p->id);
    $this->verifikasi->handle($p, $this->k->kepalaPmat);

    expect(fn () => $this->verifikasi->handle($basi, $this->k->kepalaPmat))
        ->toThrow(TransisiStatusTidakSahException::class, 'sudah diproses pengguna lain');
});

test('super-admin dan pimpinan tidak bisa menyetujui', function () {
    $p = buatPengajuanDiajukan($this->k);
    $p = $this->verifikasi->handle($p, $this->k->kepalaPmat);
    $p = $this->verifikasi->handle($p, $this->k->keu);

    foreach ([Peran::SuperAdmin, Peran::Pimpinan] as $peran) {
        $pengguna = penggunaDenganPeran($peran);
        expect($pengguna->can('setujui', $p))->toBeFalse()
            ->and(fn () => $this->setujui->handle($p, $pengguna))->toThrow(AuthorizationException::class);
    }

    expect($this->k->wadek->can('setujui', $p))->toBeTrue();
});

test('policy verifikasi mencerminkan tahap berjalan', function () {
    $p = buatPengajuanDiajukan($this->k);

    expect($this->k->kepalaPmat->can('verifikasi', $p))->toBeTrue()
        ->and($this->k->keu->can('verifikasi', $p))->toBeFalse()
        ->and($this->k->wadek->can('setujui', $p))->toBeFalse()
        ->and($this->k->adminPmat->can('verifikasi', $p))->toBeFalse();

    $p = $this->verifikasi->handle($p, $this->k->kepalaPmat);
    expect($this->k->keu->can('verifikasi', $p))->toBeTrue()
        ->and($this->k->kepalaPmat->can('verifikasi', $p))->toBeFalse();
});

test('wadek tidak bisa memakai verifikasi pada tahap wadek, dan setujui hanya dari diverifikasi_keuangan', function () {
    $p = buatPengajuanDiajukan($this->k);

    expect(fn () => $this->setujui->handle($p, $this->k->wadek))->toThrow(TransisiStatusTidakSahException::class);

    $p = $this->verifikasi->handle($p, $this->k->kepalaPmat);
    $p = $this->verifikasi->handle($p, $this->k->keu);
    expect(fn () => $this->verifikasi->handle($p, $this->k->wadek))->toThrow(TransisiStatusTidakSahException::class);
});
