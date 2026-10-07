<?php

use App\Support\Uang;

test('kali membulatkan HALF_UP skala 2', function () {
    expect(Uang::kali('2', '150000.00'))->toBe('300000.00')
        ->and(Uang::kali('0.5', '33333.33'))->toBe('16666.67')
        ->and(Uang::kali('1.005', '1'))->toBe('1.01');
});

test('jumlahkan 1000 item 0.01 tepat 10.00', function () {
    expect(Uang::jumlahkan(array_fill(0, 1000, '0.01')))->toBe('10.00')
        ->and(Uang::jumlahkan([]))->toBe('0.00');
});

test('kurang dan bandingkan', function () {
    expect(Uang::kurang('100.00', '0.01'))->toBe('99.99')
        ->and(Uang::kurang('1.00', '2.50'))->toBe('-1.50')
        ->and(Uang::bandingkan('10.00', '9.99'))->toBe(1)
        ->and(Uang::bandingkan('10.0', '10.00'))->toBe(0)
        ->and(Uang::bandingkan('1', '2'))->toBe(-1);
});

test('format rupiah indonesia', function () {
    expect(Uang::format('1234567.5'))->toBe('Rp1.234.567,50')
        ->and(Uang::format('0'))->toBe('Rp0,00')
        ->and(Uang::format('999.999'))->toBe('Rp1.000,00')
        ->and(Uang::format('-1234.5'))->toBe('-Rp1.234,50');
});

test('menolak float', function (string $metode, array $arg) {
    expect(fn () => Uang::$metode(...$arg))->toThrow(InvalidArgumentException::class);
})->with([
    'kali' => ['kali', [2.5, '100.00']],
    'kurang' => ['kurang', ['1.00', 0.5]],
    'bandingkan' => ['bandingkan', [1.0, '1.00']],
    'format' => ['format', [10.5]],
]);

test('menolak string bukan desimal', function () {
    expect(fn () => Uang::kali('abc', '1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Uang::jumlahkan([1.5]))->toThrow(InvalidArgumentException::class);
});
