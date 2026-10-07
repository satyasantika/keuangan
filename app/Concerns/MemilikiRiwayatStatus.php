<?php

namespace App\Concerns;

use App\Models\RiwayatStatus;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait MemilikiRiwayatStatus
{
    /** @return MorphMany<RiwayatStatus, $this> */
    public function riwayatStatus(): MorphMany
    {
        return $this->morphMany(RiwayatStatus::class, 'model')->orderBy('created_at');
    }
}
