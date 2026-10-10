<?php

namespace App\Policies;

use App\Enums\StatusTahunAnggaran;
use App\Models\TahunAnggaran;
use App\Models\User;

class TahunAnggaranPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, TahunAnggaran $tahunAnggaran): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('master.buat');
    }

    public function update(User $user, TahunAnggaran $tahunAnggaran): bool
    {
        return $user->can('master.ubah');
    }

    /** Hanya tahun yang masih perencanaan boleh dihapus. */
    public function delete(User $user, TahunAnggaran $tahunAnggaran): bool
    {
        return $user->can('master.hapus') && $tahunAnggaran->status === StatusTahunAnggaran::Perencanaan;
    }
}
