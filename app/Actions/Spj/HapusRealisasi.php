<?php

namespace App\Actions\Spj;

use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Models\RealisasiItem;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Soft delete realisasi beserta tautannya selama SPJ draf/dikembalikan.
 */
class HapusRealisasi
{
    public function __construct(private readonly HitungUlangTotalPengajuan $hitungUlang) {}

    public function handle(RealisasiItem $realisasi, User $oleh): void
    {
        if (! $oleh->can('delete', $realisasi)) {
            throw new AuthorizationException('Anda tidak berhak menghapus realisasi ini.');
        }

        $pengajuan = $realisasi->spj->pengajuan;

        DenganKunci::jalankan("aksi:pengajuan-dana:{$pengajuan->id}", fn () => DB::transaction(function () use ($realisasi, $pengajuan): void {
            $realisasi->tautanBerkas()->get()->each(fn (TautanBerkas $t) => $t->delete());
            $realisasi->delete();

            $this->hitungUlang->handle($pengajuan->fresh() ?? $pengajuan);
        }));
    }
}
