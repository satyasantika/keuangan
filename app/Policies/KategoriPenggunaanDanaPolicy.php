<?php

namespace App\Policies;

use App\Models\KategoriPenggunaanDana;
use App\Models\User;

class KategoriPenggunaanDanaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, KategoriPenggunaanDana $model): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('master.buat');
    }

    public function update(User $user, KategoriPenggunaanDana $model): bool
    {
        return $user->can('master.ubah');
    }

    public function delete(User $user, KategoriPenggunaanDana $model): bool
    {
        return $user->can('master.hapus');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('master.hapus');
    }

    public function restore(User $user, KategoriPenggunaanDana $model): bool
    {
        return $user->can('master.hapus');
    }

    public function reorder(User $user): bool
    {
        return $user->can('master.ubah');
    }
}
