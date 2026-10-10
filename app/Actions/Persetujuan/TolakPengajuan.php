<?php

namespace App\Actions\Persetujuan;

use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;

/**
 * Tolak (final): pengajuan → ditolak, RAB awal → ditolak.
 */
class TolakPengajuan extends KeputusanNegatif
{
    protected function statusTujuan(): StatusPengajuan
    {
        return StatusPengajuan::Ditolak;
    }

    protected function statusRabTujuan(): StatusRab
    {
        return StatusRab::Ditolak;
    }
}
