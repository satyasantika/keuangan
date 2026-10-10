<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusSpj;
use App\Models\PotonganPajak;
use App\Models\RealisasiItem;
use App\Models\User;

/**
 * Admin-unit mengisi selama SPJ draf/dikembalikan; BPP juga saat SPJ diajukan (melengkapi bukti setor).
 */
class PotonganPajakPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pajak.lihat');
    }

    public function view(User $user, PotonganPajak $potongan): bool
    {
        return $user->can('pajak.lihat') && $potongan->realisasiItem->spj->pengajuan->terlihatOleh($user);
    }

    public function create(User $user, ?RealisasiItem $realisasi = null): bool
    {
        if ($realisasi === null) {
            return $user->can('pajak.buat') || $user->can('pajak.ubah');
        }

        $status = $realisasi->spj->status;

        if ($this->unitPengaju($user, $realisasi)) {
            return $user->can('pajak.buat') && $status->dapatDiubah();
        }

        return $this->bendahara($user) && $status->dapatDiubah();
    }

    public function update(User $user, PotonganPajak $potongan): bool
    {
        $realisasi = $potongan->realisasiItem;
        $status = $realisasi->spj->status;

        if ($this->unitPengaju($user, $realisasi)) {
            return $user->can('pajak.ubah') && $status->dapatDiubah();
        }

        return $this->bendahara($user) && in_array($status, PotonganPajak::STATUS_SPJ_TERBUKA, true);
    }

    public function delete(User $user, PotonganPajak $potongan): bool
    {
        $status = $potongan->realisasiItem->spj->status;

        return $status !== StatusSpj::Diajukan && $this->update($user, $potongan);
    }

    private function unitPengaju(User $user, RealisasiItem $realisasi): bool
    {
        return $user->hasRole(Peran::AdminUnit->value) && $user->unit_id !== null && $user->unit_id === $realisasi->spj->pengajuan->unit_id;
    }

    private function bendahara(User $user): bool
    {
        return $user->hasRole(Peran::Bpp->value) && $user->can('pajak.ubah');
    }
}
