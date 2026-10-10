<?php

namespace App\Actions\Revisi;

use App\Enums\StatusRab;

/** Revisi dikembalikan ke draf untuk diperbaiki. */
class KembalikanRevisiRab extends KeputusanNegatifRevisi
{
    protected function tujuan(): StatusRab
    {
        return StatusRab::Draf;
    }
}
