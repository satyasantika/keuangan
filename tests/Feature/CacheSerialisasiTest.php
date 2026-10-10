<?php

use App\Data\ParameterLaporan;
use App\Models\Akun;
use App\Models\Unit;
use App\Queries\LaporanRealisasiQuery;
use Database\Seeders\UnitContohSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * Store cache produksi (Redis/file) men-serialize nilai dan menolak unserialize objek (cache.serializable_classes
 * = false): data tercache harus berupa larik/skalar agar tidak kembali sebagai __PHP_Incomplete_Class.
 */
beforeEach(function () {
    config(['cache.default' => 'file', 'cache.stores.file.path' => storage_path('framework/cache/uji-'.uniqid())]);
});

afterEach(function () {
    Cache::store('file')->flush();
});

test('laporan realisasi tercache tetap Collection pada panggilan kedua', function () {
    $this->travelTo('2027-06-20 10:00:00');
    $k = siapkanAnggaran();
    buatPengajuanDisetujui($k);
    $parameter = new ParameterLaporan($k->tahun->id);

    $pertama = LaporanRealisasiQuery::untuk($parameter, $k->keu);
    $kedua = LaporanRealisasiQuery::untuk($parameter, $k->keu);

    expect($kedua->all())->toBe($pertama->all())
        ->and($kedua->first()['unit_kode'])->toBe('PMAT');
});

test('akun & unit aktif tercache kembali sebagai model', function () {
    $this->seed(UnitContohSeeder::class);
    Akun::factory()->count(2)->create();

    Unit::aktifTercache();
    Akun::aktifTercache();

    expect(Unit::aktifTercache()->first())->toBeInstanceOf(Unit::class)
        ->and(Unit::aktifTercache()->pluck('kode'))->toContain('PMAT')
        ->and(Akun::aktifTercache())->toHaveCount(Akun::where('aktif', true)->count())
        ->and(Akun::aktifTercache()->first())->toBeInstanceOf(Akun::class);
});
