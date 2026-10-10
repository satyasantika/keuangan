<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengguna.lihat');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('pengguna.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('pengguna.buat');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('pengguna.ubah');
    }

    /**
     * Pengguna tidak boleh menghapus dirinya sendiri.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can('pengguna.hapus') && $user->isNot($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('pengguna.hapus');
    }

    /**
     * Reset MFA hanya oleh super-admin (F2.6).
     */
    public function resetMfa(User $user, User $model): bool
    {
        return $user->hasRole(Peran::SuperAdmin->value);
    }

    /**
     * Impersonasi darurat: hanya super-admin, bukan diri sendiri, dan akun sasaran aktif.
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->hasRole(Peran::SuperAdmin->value) && $user->isNot($model) && $model->aktif;
    }
}
