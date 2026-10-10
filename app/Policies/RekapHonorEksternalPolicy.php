<?php

namespace App\Policies;

use App\Enums\StatusPeriode;
use App\Models\RekapHonorEksternal;
use App\Models\User;

/**
 * Rekap honor fkipapp: baca-saja; impor & batalkan batch hanya admin-keuangan (BR-24). Tidak ada edit per baris.
 */
class RekapHonorEksternalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('impor.lihat');
    }

    public function view(User $user, RekapHonorEksternal $rekap): bool
    {
        return $user->can('impor.lihat') && ($user->lingkupFakultas() || ($rekap->unit_id !== null && $rekap->unit_id === $user->unit_id));
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function impor(User $user): bool
    {
        return $user->can('impor.buat');
    }

    public function update(User $user, RekapHonorEksternal $rekap): bool
    {
        return false;
    }

    public function batalkan(User $user, RekapHonorEksternal $rekap): bool
    {
        return $user->can('impor.hapus') && $rekap->status === 'aktif' && $rekap->periode->status === StatusPeriode::Terbuka;
    }

    public function delete(User $user, RekapHonorEksternal $rekap): bool
    {
        return false;
    }
}
