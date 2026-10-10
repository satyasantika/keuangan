<?php

namespace App\Policies;

use App\Enums\StatusPagu;
use App\Models\PaguAkun;
use App\Models\User;

class PaguAkunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pagu.lihat');
    }

    public function view(User $user, PaguAkun $paguAkun): bool
    {
        return $user->can('pagu.lihat') && $paguAkun->pagu->terlihatOleh($user);
    }

    public function create(User $user): bool
    {
        return $user->can('pagu.ubah');
    }

    public function update(User $user, PaguAkun $paguAkun): bool
    {
        return $user->can('pagu.ubah') && $paguAkun->pagu->status === StatusPagu::Draf;
    }

    public function delete(User $user, PaguAkun $paguAkun): bool
    {
        return $user->can('pagu.ubah') && $paguAkun->pagu->status === StatusPagu::Draf;
    }
}
