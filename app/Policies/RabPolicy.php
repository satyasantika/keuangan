<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RiwayatStatus;
use App\Models\User;

/**
 * Revisi RAB bernomor (BR-10): disusun admin-unit; diverifikasi admin-keuangan; disetujui wadek-keuangan.
 */
class RabPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('revisi.lihat');
    }

    public function view(User $user, Rab $rab): bool
    {
        return ($user->can('revisi.lihat') || $user->can('pengajuan.lihat')) && $rab->pengajuanPemilik()->terlihatOleh($user);
    }

    /** Buat revisi atas pengajuan disetujui/dicairkan yang SPJ-nya belum diajukan/diterima. */
    public function create(User $user, ?Pengajuan $pengajuan = null): bool
    {
        if (! $user->can('revisi.buat') || $user->unit_id === null) {
            return false;
        }

        if ($pengajuan === null) {
            return true;
        }

        $statusSpj = $pengajuan->spj()->value('status');

        return $pengajuan->unit_id === $user->unit_id
            && in_array($pengajuan->status, [StatusPengajuan::Disetujui, StatusPengajuan::Dicairkan], true)
            && ($statusSpj === null || $statusSpj->dapatDiubah())
            && $pengajuan->revisiBerjalan() === null;
    }

    /** Sunting item & ajukan: revisi berstatus draf milik unit pengguna. */
    public function update(User $user, Rab $rab): bool
    {
        return $rab->nomor_revisi > 0
            && $rab->status === StatusRab::Draf
            && $user->can('revisi.ubah')
            && $user->unit_id !== null
            && $user->unit_id === $rab->pengajuanPemilik()->unit_id;
    }

    public function ajukan(User $user, Rab $rab): bool
    {
        return $this->update($user, $rab);
    }

    public function verifikasi(User $user, Rab $rab): bool
    {
        return $rab->nomor_revisi > 0
            && $rab->status === StatusRab::Diajukan
            && $user->hasRole(Peran::AdminKeuangan->value)
            && $user->can('revisi.setujui')
            && ! $this->melanggarPemisahan($user, $rab, TahapPersetujuan::Keuangan);
    }

    public function setujui(User $user, Rab $rab): bool
    {
        return $rab->nomor_revisi > 0
            && $rab->status === StatusRab::DiverifikasiKeuangan
            && $user->hasRole(Peran::WadekKeuangan->value)
            && $user->can('revisi.setujui')
            && ! $this->melanggarPemisahan($user, $rab, TahapPersetujuan::Wadek);
    }

    /** Kembalikan/tolak oleh pemegang tahap berjalan. */
    public function putuskan(User $user, Rab $rab): bool
    {
        return $this->verifikasi($user, $rab) || $this->setujui($user, $rab);
    }

    public function delete(User $user, Rab $rab): bool
    {
        return false;
    }

    /**
     * BR-07: pembuat revisi tidak boleh memutuskan; satu pengguna satu tahap per revisi.
     */
    public function melanggarPemisahan(User $user, Rab $rab, TahapPersetujuan $tahap): bool
    {
        return $rab->dibuat_oleh === $user->getKey()
            || RiwayatStatus::query()
                ->where('model_type', $rab->getMorphClass())
                ->where('model_id', $rab->getKey())
                ->where('user_id', $user->getKey())
                ->whereNotNull('tahap')
                ->where('tahap', '!=', $tahap->value)
                ->exists();
    }
}
