<?php

namespace App\Policies;

use App\Models\Akun;
use App\Models\User;

class AkunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, Akun $model): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('master.buat');
    }

    public function update(User $user, Akun $model): bool
    {
        return $user->can('master.ubah');
    }

    /** Akun yang sudah dipakai RAB tidak boleh dihapus. */
    public function delete(User $user, Akun $model): bool
    {
        return $user->can('master.hapus') && ! $model->sedangDipakai();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('master.hapus');
    }

    public function restore(User $user, Akun $model): bool
    {
        return $user->can('master.hapus');
    }

    public function import(User $user): bool
    {
        return $user->can('master.buat') && $user->can('master.ubah');
    }
}
