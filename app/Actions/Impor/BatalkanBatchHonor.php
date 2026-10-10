<?php

namespace App\Actions\Impor;

use App\Models\RekapHonorEksternal;
use App\Models\User;
use App\Support\VersiCacheDasbor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan satu batch impor rekap honor (hanya bila periodenya masih terbuka — guard model).
 */
class BatalkanBatchHonor
{
    public function handle(string $batch, User $oleh): int
    {
        if (! $oleh->can('impor.hapus')) {
            throw new AuthorizationException('Anda tidak berhak membatalkan batch impor.');
        }

        $jumlah = DB::transaction(function () use ($batch): int {
            $baris = RekapHonorEksternal::query()->where('batch_impor', $batch)->where('status', 'aktif')->lockForUpdate()->get();
            $baris->each(fn (RekapHonorEksternal $r) => $r->forceFill(['status' => 'dibatalkan'])->save());

            return $baris->count();
        });

        VersiCacheDasbor::naikkan();

        return $jumlah;
    }
}
