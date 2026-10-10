<?php

namespace App\Actions\Persetujuan;

use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;

/**
 * Kembalikan untuk perbaikan: pengajuan → dikembalikan, RAB awal → draf agar dapat disunting lagi.
 */
class KembalikanPengajuan extends KeputusanNegatif
{
    protected function statusTujuan(): StatusPengajuan
    {
        return StatusPengajuan::Dikembalikan;
    }

    protected function statusRabTujuan(): StatusRab
    {
        return StatusRab::Draf;
    }
}
