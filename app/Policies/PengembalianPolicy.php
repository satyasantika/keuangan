<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusPengembalian;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\User;

/**
 * Setoran dicatat admin-unit unitnya; diverifikasi BPP yang bukan pembuatnya (BR-07).
 */
class PengembalianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengembalian.lihat');
    }

    public function view(User $user, Pengembalian $pengembalian): bool
    {
        return $user->can('pengembalian.lihat') && $pengembalian->pengajuan->terlihatOleh($user);
    }

    public function create(User $user, ?Pengajuan $pengajuan = null): bool
    {
        return $user->can('pengembalian.buat')
            && $user->unit_id !== null
            && ($pengajuan === null || $pengajuan->unit_id === $user->unit_id);
    }

    /** "Ubah" = kelola tautan bukti setor selama status diajukan. */
    public function update(User $user, Pengembalian $pengembalian): bool
    {
        return $pengembalian->status === StatusPengembalian::Diajukan
            && $this->create($user, $pengembalian->pengajuan);
    }

    public function verifikasi(User $user, Pengembalian $pengembalian): bool
    {
        return $pengembalian->status === StatusPengembalian::Diajukan
            && $user->hasRole(Peran::Bpp->value)
            && $user->can('pengembalian.setujui')
            && $pengembalian->dibuat_oleh !== $user->getKey();
    }

    public function delete(User $user, Pengembalian $pengembalian): bool
    {
        return false;
    }
}
