<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusPencairan;
use App\Models\Pencairan;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Pencairan dicatat/dibatalkan oleh BPP; unit hanya melihat pencairan unitnya. Tidak ada penghapusan (BR-22).
 */
class PencairanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pencairan.lihat');
    }

    public function view(User $user, Pencairan $pencairan): bool
    {
        return $user->can('pencairan.lihat') && $pencairan->pengajuan->terlihatOleh($user);
    }

    public function create(User $user, ?Pengajuan $pengajuan = null): bool
    {
        return $user->hasRole(Peran::Bpp->value) && $user->can('pencairan.buat');
    }

    /** "Ubah" = kelola tautan bukti selama pencairan aktif (pembatalan lewat ability batalkan). */
    public function update(User $user, Pencairan $pencairan): bool
    {
        return $user->hasRole(Peran::Bpp->value) && $user->can('pencairan.ubah') && $pencairan->status === StatusPencairan::Dicatat;
    }

    public function batalkan(User $user, Pencairan $pencairan): bool
    {
        return $this->update($user, $pencairan);
    }

    public function delete(User $user, Pencairan $pencairan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
