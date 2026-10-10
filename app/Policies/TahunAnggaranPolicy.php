<?php

namespace App\Policies;

use App\Enums\Peran;
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

    /** Penutupan tahun hanya oleh wadek-keuangan (BR-20). */
    public function tutup(User $user, TahunAnggaran $tahunAnggaran): bool
    {
        return $user->hasRole(Peran::WadekKeuangan->value) && $user->can('periode.setujui');
    }

    /** Hanya tahun yang masih perencanaan boleh dihapus. */
    public function delete(User $user, TahunAnggaran $tahunAnggaran): bool
    {
        return $user->can('master.hapus') && $tahunAnggaran->status === StatusTahunAnggaran::Perencanaan;
    }
}
