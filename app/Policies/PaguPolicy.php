<?php

namespace App\Policies;

use App\Enums\StatusPagu;
use App\Models\Pagu;
use App\Models\User;

/**
 * Pengguna unit hanya melihat pagu unitnya (BR-21).
 */
class PaguPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pagu.lihat');
    }

    public function view(User $user, Pagu $pagu): bool
    {
        return $user->can('pagu.lihat') && $pagu->terlihatOleh($user);
    }

    public function create(User $user): bool
    {
        return $user->can('pagu.buat');
    }

    public function update(User $user, Pagu $pagu): bool
    {
        return $user->can('pagu.ubah') && $pagu->status === StatusPagu::Draf;
    }

    /** Hanya draf yang boleh dihapus (BR-22). */
    public function delete(User $user, Pagu $pagu): bool
    {
        return $user->can('pagu.ubah') && $pagu->status === StatusPagu::Draf;
    }

    public function tetapkan(User $user, Pagu $pagu): bool
    {
        return $user->can('pagu.setujui') && $pagu->status === StatusPagu::Draf;
    }

    public function ubahDitetapkan(User $user, Pagu $pagu): bool
    {
        return $user->can('pagu.setujui') && $pagu->status === StatusPagu::Ditetapkan;
    }
}
