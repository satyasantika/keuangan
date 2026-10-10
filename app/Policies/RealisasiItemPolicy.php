<?php

namespace App\Policies;

use App\Models\RealisasiItem;
use App\Models\User;

/**
 * Realisasi disusun admin-unit unit pengajuan selama SPJ draf/dikembalikan.
 */
class RealisasiItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('spj.lihat');
    }

    public function view(User $user, RealisasiItem $realisasi): bool
    {
        return $user->can('spj.lihat') && $realisasi->spj->pengajuan->terlihatOleh($user);
    }

    public function create(User $user): bool
    {
        return $user->can('spj.buat') && $user->unit_id !== null;
    }

    public function update(User $user, RealisasiItem $realisasi): bool
    {
        return $user->can('spj.ubah')
            && $user->unit_id !== null
            && $user->unit_id === $realisasi->spj->pengajuan->unit_id
            && $realisasi->spjDapatDiubah();
    }

    public function delete(User $user, RealisasiItem $realisasi): bool
    {
        return $this->update($user, $realisasi);
    }
}
