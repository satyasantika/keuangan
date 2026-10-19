<?php

namespace App\Support;

use App\Exceptions\AksiSedangDiprosesException;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Lock cache per kunci (BR-09; Profil Ringan: store `database`, tabel cache_locks) agar aksi tidak berjalan dobel.
 */
final class DenganKunci
{
    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public static function jalankan(string $kunci, Closure $fn, int $ttl = 15, int $tunggu = 8): mixed
    {
        try {
            /** @var LockProvider $store */
            $store = Cache::store(config('keuangan.lock_store'))->getStore();

            return $store
                ->lock("lock:{$kunci}", $ttl)
                ->block($tunggu, $fn);
        } catch (LockTimeoutException) {
            throw new AksiSedangDiprosesException('Sedang diproses, coba lagi.');
        }
    }
}
