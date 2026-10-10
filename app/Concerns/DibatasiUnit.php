<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembatasan data per unit (BR-21) untuk model yang memiliki kolom unit_id.
 * Pengguna tanpa peran fakultas hanya melihat baris unitnya; tanpa unit tidak melihat apa pun.
 */
trait DibatasiUnit
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTerlihatOleh(Builder $query, User $pengguna): Builder
    {
        if ($pengguna->lingkupFakultas()) {
            return $query;
        }

        if ($pengguna->unit_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('unit_id'), $pengguna->unit_id);
    }

    public function terlihatOleh(User $pengguna): bool
    {
        return $pengguna->lingkupFakultas()
            || ($pengguna->unit_id !== null && $this->getAttribute('unit_id') === $pengguna->unit_id);
    }
}
