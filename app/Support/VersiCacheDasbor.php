<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache dasbor/laporan berbasis versi: menaikkan versi membuat cache lama kedaluwarsa sendiri.
 */
final class VersiCacheDasbor
{
    public const KUNCI = 'dasbor:versi';

    public static function versi(): int
    {
        return (int) Cache::get(self::KUNCI, 1);
    }

    public static function naikkan(): int
    {
        if (! Cache::has(self::KUNCI)) {
            Cache::forever(self::KUNCI, 1);
        }

        return (int) Cache::increment(self::KUNCI);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $hitung
     * @return T
     */
    public static function ingat(string $kunci, Closure $hitung, int $detik = 600): mixed
    {
        return Cache::remember('dasbor:v'.self::versi().':'.$kunci, $detik, $hitung);
    }
}
