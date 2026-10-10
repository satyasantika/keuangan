<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('master.buat');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->can('master.ubah');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->can('master.hapus');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('master.hapus');
    }

    public function restore(User $user, Unit $unit): bool
    {
        return $user->can('master.hapus');
    }

    public function import(User $user): bool
    {
        return $user->can('master.buat') && $user->can('master.ubah');
    }
}
