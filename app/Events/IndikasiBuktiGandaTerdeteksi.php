<?php

namespace App\Events;

use App\Models\RealisasiItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tautan bukti realisasi sama dengan bukti realisasi pengajuan lain (BR-15). Listener notifikasi di F10.
 */
class IndikasiBuktiGandaTerdeteksi implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $realisasiLainIds
     */
    public function __construct(public RealisasiItem $realisasi, public array $realisasiLainIds) {}
}
