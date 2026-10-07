<?php

use App\Exceptions\AksiSedangDiprosesException;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PaguTidakCukupException;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\PeriodeTerkunciException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Support\DenganKunci;
use App\Support\Penomoran;
use Illuminate\Support\Facades\Cache;

test('50 panggilan menghasilkan nomor unik berurutan', function () {
    $nomor = collect(range(1, 50))->map(fn () => Penomoran::berikutnya('pengajuan', 2027, 'PGJ/{tahun}/{urut}'));

    expect($nomor->first())->toBe('PGJ/2027/0001')
        ->and($nomor->last())->toBe('PGJ/2027/0050')
        ->and($nomor->unique())->toHaveCount(50);
});

test('tahun berbeda dan jenis berbeda mulai dari 0001', function () {
    Penomoran::berikutnya('pengajuan', 2027, 'PGJ/{tahun}/{urut}');

    expect(Penomoran::berikutnya('pengajuan', 2028, 'PGJ/{tahun}/{urut}'))->toBe('PGJ/2028/0001')
        ->and(Penomoran::berikutnya('spj', 2027, 'SPJ/{tahun}/{urut}'))->toBe('SPJ/2027/0001');
});

test('tabel penomoran punya indeks unik jenis dan tahun', function () {
    $unik = collect(Schema::getIndexes('penomoran'))->firstWhere(fn ($i) => $i['unique'] && $i['columns'] === ['jenis', 'tahun']);

    expect($unik)->not->toBeNull();
});

test('DenganKunci mengembalikan hasil closure dan melepas kunci', function () {
    expect(DenganKunci::jalankan('uji:a', fn () => 'ok'))->toBe('ok')
        ->and(DenganKunci::jalankan('uji:a', fn () => 'lagi'))->toBe('lagi');
});

test('panggilan kedua saat kunci dipegang melempar AksiSedangDiprosesException', function () {
    $lock = Cache::store(config('keuangan.lock_store'))->lock('lock:uji:b', 10);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => DenganKunci::jalankan('uji:b', fn () => 'x', 10, 1))
            ->toThrow(AksiSedangDiprosesException::class, 'Sedang diproses, coba lagi.');
    } finally {
        $lock->release();
    }
});

test('semua exception domain turunan KesalahanDomain', function (string $kelas) {
    expect(new $kelas)->toBeInstanceOf(KesalahanDomain::class)
        ->and((new $kelas)->getMessage())->not->toBeEmpty();
})->with([
    AksiSedangDiprosesException::class,
    PeriodeTerkunciException::class,
    PaguTidakCukupException::class,
    PelanggaranPemisahanTugasException::class,
    TransisiStatusTidakSahException::class,
]);
