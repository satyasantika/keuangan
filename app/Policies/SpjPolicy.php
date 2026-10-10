<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusSpj;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Models\User;

/**
 * SPJ: diajukan admin-unit unitnya; diverifikasi admin-keuangan/BPP yang bukan pembuat SPJ/realisasi (BR-07).
 */
class SpjPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('spj.lihat');
    }

    public function view(User $user, Spj $spj): bool
    {
        return $user->can('spj.lihat') && $spj->pengajuan->terlihatOleh($user);
    }

    public function ajukan(User $user, Spj $spj): bool
    {
        return $user->can('spj.ubah')
            && $user->unit_id !== null
            && $user->unit_id === $spj->pengajuan->unit_id
            && $spj->status->dapatDiubah();
    }

    public function verifikasi(User $user, Spj $spj): bool
    {
        return $spj->status === StatusSpj::Diajukan
            && $user->can('spj.setujui')
            && $user->hasAnyRole([Peran::AdminKeuangan->value, Peran::Bpp->value])
            && ! $this->terlibatMenyusun($user, $spj);
    }

    /**
     * Pembuat SPJ atau pencatat salah satu realisasinya.
     */
    public function terlibatMenyusun(User $user, Spj $spj): bool
    {
        return $spj->dibuat_oleh === $user->getKey()
            || RealisasiItem::withTrashed()->where('spj_id', $spj->id)->where('dibuat_oleh', $user->getKey())->exists();
    }

    public function delete(User $user, Spj $spj): bool
    {
        return false;
    }
}
