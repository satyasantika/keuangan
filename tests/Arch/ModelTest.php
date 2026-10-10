<?php

use App\Models\BarisImporGagal;
use App\Models\Ekspor;
use App\Models\Impor;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return list<class-string<Model>>
 */
function semuaModelAplikasi(): array
{
    $kelas = [];

    foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') as $berkas) {
        $nama = 'App\\Models\\'.basename($berkas, '.php');

        if (is_subclass_of($nama, Model::class) && ! (new ReflectionClass($nama))->isAbstract()) {
            $kelas[] = $nama;
        }
    }

    return $kelas;
}

test('setiap model di App\Models mendefinisikan properti $table sendiri', function () {
    $pelanggaran = collect(semuaModelAplikasi())
        ->reject(fn (string $kelas) => (new ReflectionProperty($kelas, 'table'))->getDeclaringClass()->getName() === $kelas)
        ->values()
        ->all();

    expect($pelanggaran)->toBe([]);
});

test('tidak ada model yang memasukkan status ke $fillable', function () {
    $pelanggaran = collect(semuaModelAplikasi())
        ->filter(fn (string $kelas) => in_array('status', (new $kelas)->getFillable(), true))
        ->values()
        ->all();

    expect($pelanggaran)->toBe([]);
});

test('kolom uang di $casts bertipe decimal:2', function () {
    $pelanggaran = [];
    // Model paket Filament (hitungan baris impor/ekspor, bukan uang).
    $paket = [Impor::class, Ekspor::class, BarisImporGagal::class];

    foreach (array_diff(semuaModelAplikasi(), $paket) as $kelas) {
        foreach ((new $kelas)->getCasts() as $kolom => $cast) {
            $kolomUang = in_array($kolom, ['jumlah', 'pagu', 'harga_satuan', 'nilai'], true)
                || str_starts_with($kolom, 'total')
                || str_starts_with($kolom, 'jumlah_');

            if ($kolomUang && $cast !== 'decimal:2') {
                $pelanggaran[] = "{$kelas}::{$kolom} ({$cast})";
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});
