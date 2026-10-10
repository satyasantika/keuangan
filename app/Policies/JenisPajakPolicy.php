<?php

namespace App\Policies;

use App\Models\JenisPajak;
use App\Models\User;

class JenisPajakPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, JenisPajak $model): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('master.buat');
    }

    public function update(User $user, JenisPajak $model): bool
    {
        return $user->can('master.ubah');
    }

    public function delete(User $user, JenisPajak $model): bool
    {
        return $user->can('master.hapus');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('master.hapus');
    }

    public function restore(User $user, JenisPajak $model): bool
    {
        return $user->can('master.hapus');
    }
}
