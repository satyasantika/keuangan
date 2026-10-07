<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Aritmetika uang presisi (BR-01): string desimal, skala 2, pembulatan HALF_UP.
 * Dilarang menerima/mengembalikan float: parameter diketik lebar agar float yang
 * dikirim pemanggil (tanpa strict_types) ditolak dengan InvalidArgumentException,
 * bukan dikonversi diam-diam menjadi string.
 */
final class Uang
{
    public const SKALA = 2;

    public static function kali(string|int|float $volume, string|int|float $harga): string
    {
        return self::keString(self::angka($volume)->multipliedBy(self::angka($harga)));
    }

    /**
     * @param  iterable<string>  $nilai
     */
    public static function jumlahkan(iterable $nilai): string
    {
        $total = BigDecimal::zero();

        foreach ($nilai as $n) {
            $total = $total->plus(self::angka($n));
        }

        return self::keString($total);
    }

    public static function kurang(string|int|float $a, string|int|float $b): string
    {
        return self::keString(self::angka($a)->minus(self::angka($b)));
    }

    public static function bandingkan(string|int|float $a, string|int|float $b): int
    {
        return self::angka($a)->compareTo(self::angka($b));
    }

    /**
     * Format rupiah Indonesia: Rp1.234.567,00 (negatif: -Rp1.234,00).
     */
    public static function format(string|int|float $nilai): string
    {
        $angka = self::angka($nilai)->toScale(self::SKALA, RoundingMode::HalfUp);
        $negatif = $angka->isNegative();
        [$bulat, $pecahan] = explode('.', (string) $angka->abs());
        $bulat = strrev(implode('.', str_split(strrev($bulat), 3)));

        return ($negatif ? '-' : '').'Rp'.$bulat.','.$pecahan;
    }

    private static function angka(mixed $nilai): BigDecimal
    {
        if (is_float($nilai)) {
            throw new InvalidArgumentException('Nilai uang tidak boleh bertipe float; gunakan string desimal.');
        }

        if (is_int($nilai)) {
            $nilai = (string) $nilai;
        }

        if (! is_string($nilai) || ! preg_match('/^-?\d+(\.\d+)?$/', $nilai)) {
            throw new InvalidArgumentException('Nilai uang harus berupa string desimal, mis. "1500000.00".');
        }

        return BigDecimal::of($nilai);
    }

    private static function keString(BigDecimal $nilai): string
    {
        return (string) $nilai->toScale(self::SKALA, RoundingMode::HalfUp);
    }
}
