<?php

namespace App\Policies;

use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Hak atas tautan mengikuti Policy pemiliknya: lihat → view pemilik; tambah/ubah/hapus → update pemilik.
 */
class TautanBerkasPolicy
{
    /** Daftar hanya dirender di halaman pemilik yang sudah lolos Policy view. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TautanBerkas $tautan): bool
    {
        $pemilik = $tautan->pemilik;

        return $pemilik !== null && $user->can('view', $pemilik);
    }

    public function create(User $user, ?Model $pemilik = null): bool
    {
        return $pemilik !== null && $user->can('update', $pemilik);
    }

    public function update(User $user, TautanBerkas $tautan): bool
    {
        $pemilik = $tautan->pemilik;

        return $pemilik !== null && $user->can('update', $pemilik);
    }

    public function delete(User $user, TautanBerkas $tautan): bool
    {
        return $this->update($user, $tautan);
    }
}
