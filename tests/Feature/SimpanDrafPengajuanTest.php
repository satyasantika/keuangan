<?php

use App\Actions\Master\UbahStatusTahunAnggaran;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Models\Akun;
use App\Models\Pengajuan;
use App\Models\Unit;
use Database\Seeders\UnitContohSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(UnitContohSeeder::class);
    $this->tahun = buatTahun(2027);
    $this->pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $this->pbio = Unit::where('kode', 'PBIO')->firstOrFail();
    $this->adminPmat = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]);
    $this->akun = Akun::factory()->create();
    $this->aksi = app(SimpanDrafPengajuan::class);
});

test('(a) jumlah dan total dihitung server walau klien mengirim total lain', function () {
    $pengajuan = $this->aksi->handle(
        dataKegiatan($this->tahun->id, ['total_diajukan' => '1']),
        [itemRab($this->akun->id, '2', '150000', ['jumlah' => '1', 'total' => '1'])],
        $this->adminPmat,
    );

    $rab = $pengajuan->rabAktif;
    expect($pengajuan->status)->toBe(StatusPengajuan::Draf)
        ->and($rab->nomor_revisi)->toBe(0)
        ->and($rab->status)->toBe(StatusRab::Draf)
        ->and($rab->item()->first()->jumlah)->toBe('300000.00')
        ->and($rab->total)->toBe('300000.00')
        ->and($pengajuan->total_diajukan)->toBe('300000.00');
});

test('(b) pembulatan HALF_UP per item: 0.5 × 33333.33 = 16666.67', function () {
    $pengajuan = $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id, '0.5', '33333.33')], $this->adminPmat);

    expect($pengajuan->rabAktif->item()->first()->jumlah)->toBe('16666.67')
        ->and($pengajuan->total_diajukan)->toBe('16666.67');
});

test('(c) total tiga item tepat dan urutan item mengikuti array', function () {
    $pengajuan = $this->aksi->handle(dataKegiatan($this->tahun->id), [
        itemRab($this->akun->id, '3', '33333.33', ['uraian' => 'A']),
        itemRab($this->akun->id, '1.5', '10000.01', ['uraian' => 'B']),
        itemRab($this->akun->id, '7', '0.07', ['uraian' => 'C']),
    ], $this->adminPmat);

    // 99999.99 + 15000.015→15000.02 + 0.49 = 115000.50
    $item = $pengajuan->rabAktif->item()->get();
    expect($item->pluck('uraian')->all())->toBe(['A', 'B', 'C'])
        ->and($item->pluck('jumlah')->all())->toBe(['99999.99', '15000.02', '0.49'])
        ->and($pengajuan->total_diajukan)->toBe('115000.50');

    // Simpan ulang mengganti seluruh item.
    $pengajuan = $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id, '1', '5000')], $this->adminPmat, $pengajuan);
    expect($pengajuan->rabAktif->item()->count())->toBe(1)
        ->and($pengajuan->total_diajukan)->toBe('5000.00');
});

test('(d) admin-unit PMAT tidak bisa memperbarui pengajuan PBIO', function () {
    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pbio->id]);
    $pengajuanPbio = $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id)], $adminPbio);

    expect($this->adminPmat->can('update', $pengajuanPbio))->toBeFalse()
        ->and($this->adminPmat->can('view', $pengajuanPbio))->toBeFalse()
        ->and(fn () => $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id)], $this->adminPmat, $pengajuanPbio))
        ->toThrow(AuthorizationException::class);
});

test('(e) pengajuan diajukan tidak bisa diubah via Action maupun update langsung', function () {
    $pengajuan = $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id)], $this->adminPmat);
    $pengajuan->forceFill(['status' => StatusPengajuan::Diajukan])->save();

    expect(fn () => $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id)], $this->adminPmat, $pengajuan))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $pengajuan->update(['judul_kegiatan' => 'Ganti']))
        ->toThrow(KesalahanDomain::class);

    // Perubahan status/total oleh Action tetap diizinkan.
    $pengajuan->fresh()->forceFill(['total_disetujui' => '300000.00'])->save();
    expect($pengajuan->fresh()->total_disetujui)->toBe('300000.00');
});

test('(f) item pada RAB disetujui tidak bisa dihapus atau diubah', function () {
    $pengajuan = $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id)], $this->adminPmat);
    $rab = $pengajuan->rabAktif;
    $rab->forceFill(['status' => StatusRab::Disetujui])->save();
    $item = $rab->item()->first();

    expect(fn () => $item->delete())->toThrow(KesalahanDomain::class, 'RAB sudah terkunci')
        ->and(fn () => $item->update(['uraian' => 'x']))->toThrow(KesalahanDomain::class, 'RAB sudah terkunci');
});

test('(g) unit_id kiriman klien diabaikan untuk admin-unit', function () {
    $pengajuan = $this->aksi->handle(dataKegiatan($this->tahun->id, ['unit_id' => $this->pbio->id]), [itemRab($this->akun->id)], $this->adminPmat);

    expect($pengajuan->unit_id)->toBe($this->pmat->id)
        ->and($pengajuan->dibuat_oleh)->toBe($this->adminPmat->id);
});

test('(h) tahun anggaran ditutup ditolak', function () {
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);
    app(UbahStatusTahunAnggaran::class)->handle($this->tahun, StatusTahunAnggaran::Berjalan, $keu);
    tutupTahunPaksa($this->tahun);

    expect(fn () => $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id)], $this->adminPmat))
        ->toThrow(KesalahanDomain::class, 'ditutup');
    expect(Pengajuan::count())->toBe(0);
});

test('validasi item: volume 0, harga negatif, tanpa item, akun nonaktif, tanggal terbalik ditolak', function () {
    $nonaktif = Akun::factory()->create(['aktif' => false]);

    foreach ([
        [dataKegiatan($this->tahun->id), [itemRab($this->akun->id, '0')], 'items.0.volume'],
        [dataKegiatan($this->tahun->id), [itemRab($this->akun->id, '1', '-1')], 'items.0.harga_satuan'],
        [dataKegiatan($this->tahun->id), [], 'items'],
        [dataKegiatan($this->tahun->id), [itemRab($nonaktif->id)], 'items.0.akun_id'],
        [dataKegiatan($this->tahun->id, ['tanggal_selesai_kegiatan' => '2027-03-01']), [itemRab($this->akun->id)], 'data.tanggal_selesai_kegiatan'],
        [dataKegiatan($this->tahun->id), [itemRab($this->akun->id, '1', '1000', ['satuan' => ''])], 'items.0.satuan'],
    ] as [$data, $items, $kunci]) {
        try {
            $this->aksi->handle($data, $items, $this->adminPmat);
            $this->fail("Validasi {$kunci} seharusnya gagal.");
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($kunci);
        }
    }

    expect(Pengajuan::count())->toBe(0);
});

test('policy: pengguna tanpa unit tidak boleh membuat; hapus hanya draf tanpa nomor', function () {
    $tanpaUnit = penggunaDenganPeran(Peran::AdminUnit);
    expect($tanpaUnit->can('create', Pengajuan::class))->toBeFalse();

    $pengajuan = $this->aksi->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id)], $this->adminPmat);
    expect($this->adminPmat->can('delete', $pengajuan))->toBeTrue();

    $pengajuan->forceFill(['status' => StatusPengajuan::Dikembalikan, 'nomor' => 'PGJ/2027/0001'])->save();
    expect($this->adminPmat->can('delete', $pengajuan))->toBeFalse()
        ->and($this->adminPmat->can('update', $pengajuan))->toBeTrue();
});
