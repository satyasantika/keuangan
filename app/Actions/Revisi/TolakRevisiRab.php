<?php

namespace App\Actions\Revisi;

use App\Enums\StatusRab;

/** Revisi ditolak (final); RAB aktif tidak berubah. */
class TolakRevisiRab extends KeputusanNegatifRevisi
{
    protected function tujuan(): StatusRab
    {
        return StatusRab::Ditolak;
    }
}
