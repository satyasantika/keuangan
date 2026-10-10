<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\User;

/**
 * Periode dibuat bersama tahun anggaran; dikunci admin-keuangan, dibuka wadek-keuangan (BR-19).
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

    public function kunci(User $user, Periode $periode): bool
    {
        return $periode->status === StatusPeriode::Terbuka
            && $user->hasRole(Peran::AdminKeuangan->value)
            && $user->can('periode.setujui');
    }

    public function bukaKunci(User $user, Periode $periode): bool
    {
        return $periode->status === StatusPeriode::Terkunci
            && $user->hasRole(Peran::WadekKeuangan->value)
            && $user->can('periode.setujui');
    }

    public function lihatSnapshot(User $user, Periode $periode): bool
    {
        return $user->can('periode.lihat');
    }
}
