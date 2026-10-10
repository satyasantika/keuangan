<?php

namespace App\Policies;

use App\Models\Periode;
use App\Models\User;

/**
 * Periode dibuat otomatis bersama tahun anggaran; kunci/buka kunci lewat Action F9.
 */
class PeriodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, Periode $periode): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Periode $periode): bool
    {
        return false;
    }

    public function delete(User $user, Periode $periode): bool
    {
        return false;
    }
}
