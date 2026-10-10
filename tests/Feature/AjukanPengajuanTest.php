<?php

use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\BatalkanPengajuan;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Exceptions\AksiSedangDiprosesException;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PaguTidakCukupException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Models\Pagu;
use App\Models\PaguAkun;
use App\Models\Pengajuan;
use App\Models\RiwayatStatus;
use App\Support\Pengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->k = siapkanAnggaran('100000000.00');
    $this->aksi = app(AjukanPengajuan::class);
});

test('sukses: status diajukan, nomor PGJ/2027/0001, riwayat tercatat, RAB ikut diajukan, snapshot pengaturan', function () {
    Pengaturan::set('wajib_persetujuan_wadek', false, $this->k->keu);
    $pengajuan = $this->aksi->handle(buatPengajuanDraf($this->k), $this->k->adminPmat);

    expect($pengajuan->status)->toBe(StatusPengajuan::Diajukan)
        ->and($pengajuan->nomor)->toBe('PGJ/2027/0001')
        ->and($pengajuan->diajukan_pada)->not->toBeNull()
        ->and($pengajuan->wajib_kepala_unit)->toBeTrue()
        ->and($pengajuan->wajib_wadek)->toBeFalse()
        ->and($pengajuan->rabAwal->status)->toBe(StatusRab::Diajukan);

    $riwayat = RiwayatStatus::where('model_type', 'pengajuan')->where('model_id', $pengajuan->id)->get();
    expect($riwayat)->toHaveCount(1)
        ->and($riwayat[0]->dari)->toBe('draf')
        ->and($riwayat[0]->ke)->toBe('diajukan')
        ->and($riwayat[0]->catatan)->toBe('Diajukan');

    expect(fn () => $pengajuan->update(['judul_kegiatan' => 'Ubah']))->toThrow(KesalahanDomain::class);
});

test('total melebihi sisa pagu ditolak dengan pesan berisi sisa', function () {
    buatPengajuanDiajukan($this->k, '1', '70000000');
    $besar = buatPengajuanDraf($this->k, '1', '40000000');

    expect(fn () => $this->aksi->handle($besar, $this->k->adminPmat))
        ->toThrow(PaguTidakCukupException::class, 'Rp30.000.000,00');
    expect($besar->fresh()->status)->toBe(StatusPengajuan::Draf)
        ->and($besar->fresh()->nomor)->toBeNull();
});

test('rincian pagu per akun membatasi item akun tersebut', function () {
    PaguAkun::query()->getConnection()->table('pagu')->where('id', $this->k->pagu->id)->update(['status' => 'draf']);
    PaguAkun::create(['pagu_id' => $this->k->pagu->id, 'akun_id' => $this->k->akun->id, 'jumlah' => '1000000.00']);
    PaguAkun::query()->getConnection()->table('pagu')->where('id', $this->k->pagu->id)->update(['status' => 'ditetapkan']);

    expect(fn () => $this->aksi->handle(buatPengajuanDraf($this->k, '1', '1500000'), $this->k->adminPmat))
        ->toThrow(PaguTidakCukupException::class, 'sisa pagu akun');
});

test('tanpa TOR ditolak', function () {
    expect(fn () => $this->aksi->handle(buatPengajuanDraf($this->k, denganTor: false), $this->k->adminPmat))
        ->toThrow(KesalahanDomain::class, 'TOR');
});

test('pagu draf (belum ditetapkan) ditolak', function () {
    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    Pagu::factory()->create(['tahun_anggaran_id' => $this->k->tahun->id, 'unit_id' => $this->k->pbio->id, 'dibuat_oleh' => $this->k->keu->id]);

    expect(fn () => $this->aksi->handle(buatPengajuanDraf($this->k, oleh: $adminPbio), $adminPbio))
        ->toThrow(PaguTidakCukupException::class, 'belum ditetapkan');
});

test('pengajuan ulang setelah dikembalikan mempertahankan nomor', function () {
    $pengajuan = buatPengajuanDiajukan($this->k);
    $pengajuan->forceFill(['status' => StatusPengajuan::Dikembalikan])->save();
    $pengajuan->rabAwal->forceFill(['status' => StatusRab::Draf])->save();

    $ulang = $this->aksi->handle($pengajuan->fresh(), $this->k->adminPmat);

    expect($ulang->nomor)->toBe('PGJ/2027/0001')
        ->and(RiwayatStatus::where('model_id', $pengajuan->id)->latest('id')->value('catatan'))->toBe('Diajukan ulang');
});

test('dua pengajuan berbeda mendapat nomor 0001 dan 0002', function () {
    $a = buatPengajuanDiajukan($this->k);
    $b = buatPengajuanDiajukan($this->k);

    expect([$a->nomor, $b->nomor])->toBe(['PGJ/2027/0001', 'PGJ/2027/0002']);
});

test('dobel klik saat lock dipegang → AksiSedangDiprosesException tanpa nomor kedua', function () {
    $pengajuan = buatPengajuanDraf($this->k);
    $lock = Cache::store(config('keuangan.lock_store'))->lock("lock:aksi:pengajuan:{$pengajuan->id}", 15);
    $lock->get();

    expect(fn () => $this->aksi->handle($pengajuan, $this->k->adminPmat))->toThrow(AksiSedangDiprosesException::class);
    $lock->release();

    expect($pengajuan->fresh()->nomor)->toBeNull()
        ->and(Pengajuan::whereNotNull('nomor')->count())->toBe(0);
})->group('lambat');

test('admin-unit unit lain → 403 (AuthorizationException)', function () {
    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);

    expect(fn () => $this->aksi->handle(buatPengajuanDraf($this->k), $adminPbio))->toThrow(AuthorizationException::class);
});

test('batalkan: draf dengan alasan → dibatalkan; status diajukan tidak bisa dibatalkan admin-unit', function () {
    $batal = app(BatalkanPengajuan::class);
    $draf = buatPengajuanDraf($this->k);

    expect(fn () => $batal->handle($draf, 'pendek', $this->k->adminPmat))->toThrow(KesalahanDomain::class, 'minimal');

    $batal->handle($draf, 'Kegiatan ditunda tahun depan', $this->k->adminPmat);
    expect($draf->fresh()->status)->toBe(StatusPengajuan::Dibatalkan)
        ->and($draf->fresh()->alasan_batal)->toBe('Kegiatan ditunda tahun depan');

    $diajukan = buatPengajuanDiajukan($this->k);
    expect(fn () => $batal->handle($diajukan, 'Kegiatan ditunda tahun depan', $this->k->adminPmat))
        ->toThrow(AuthorizationException::class);
    expect($diajukan->fresh()->status)->toBe(StatusPengajuan::Diajukan);
});

test('tombol Ajukan di halaman view mengajukan dan menampilkan notifikasi gagal bila pagu kurang', function () {
    $pengajuan = buatPengajuanDraf($this->k);

    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $pengajuan->getRouteKey()])
        ->callAction('ajukan')
        ->assertNotified('Pengajuan PGJ/2027/0001 diajukan');

    expect($pengajuan->fresh()->status)->toBe(StatusPengajuan::Diajukan);

    $besar = buatPengajuanDraf($this->k, '1', '999999999');
    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $besar->getRouteKey()])
        ->callAction('ajukan')
        ->assertNotified('Gagal mengajukan');

    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $pengajuan->getRouteKey()])
        ->assertActionHidden('ajukan')
        ->assertActionHidden('batalkan');
});

test('status bukan draf/dikembalikan tidak dapat diajukan', function () {
    $pengajuan = buatPengajuanDiajukan($this->k);

    expect(fn () => $this->aksi->handle($pengajuan, $this->k->adminPmat))->toThrow(AuthorizationException::class);
    expect(fn () => (new ReflectionMethod(AjukanPengajuan::class, 'ajukan'))->invoke($this->aksi, $pengajuan, 2027, $this->k->adminPmat))
        ->toThrow(TransisiStatusTidakSahException::class);
});
