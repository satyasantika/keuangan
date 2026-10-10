<?php

namespace App\Actions\Master;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPagu;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pagu;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\VersiCacheDasbor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Penetapan pagu draf oleh wadek-keuangan (US-PAGU-02).
 */
class TetapkanPagu
{
    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Pagu $pagu, User $oleh, ?string $catatan = null): Pagu
    {
        if (! $oleh->can('pagu.setujui')) {
            throw new AuthorizationException('Hanya wakil dekan keuangan yang dapat menetapkan pagu.');
        }

        return DenganKunci::jalankan("aksi:pagu:{$pagu->id}", function () use ($pagu, $oleh, $catatan): Pagu {
            return DB::transaction(function () use ($pagu, $oleh, $catatan): Pagu {
                $terkini = Pagu::query()->lockForUpdate()->findOrFail($pagu->getKey());
                $dari = $terkini->status;

                if ($dari !== StatusPagu::Draf) {
                    throw new TransisiStatusTidakSahException('Hanya pagu berstatus draf yang dapat ditetapkan.');
                }

                $terkini->forceFill([
                    'status' => StatusPagu::Ditetapkan,
                    'ditetapkan_oleh' => $oleh->getKey(),
                    'ditetapkan_pada' => now(),
                ])->save();

                $this->catatRiwayat->handle($terkini, $dari, StatusPagu::Ditetapkan, $oleh, $catatan);
                VersiCacheDasbor::naikkan();

                return $terkini;
            });
        });
    }
}
