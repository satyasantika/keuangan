<?php

namespace App\Policies;

use App\Models\LaporanEkspor;
use App\Models\User;

/**
 * Riwayat ekspor hanya untuk pemintanya.
 */
class LaporanEksporPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('laporan.ekspor');
    }

    public function view(User $user, LaporanEkspor $ekspor): bool
    {
        return $ekspor->diminta_oleh === $user->getKey();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, LaporanEkspor $ekspor): bool
    {
        return false;
    }
}
