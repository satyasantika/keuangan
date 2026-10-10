<?php

namespace App\Policies;

use App\Models\JenisPencairan;
use App\Models\User;

class JenisPencairanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, JenisPencairan $model): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('master.buat');
    }

    public function update(User $user, JenisPencairan $model): bool
    {
        return $user->can('master.ubah');
    }

    public function delete(User $user, JenisPencairan $model): bool
    {
        return $user->can('master.hapus');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('master.hapus');
    }

    public function restore(User $user, JenisPencairan $model): bool
    {
        return $user->can('master.hapus');
    }
}
