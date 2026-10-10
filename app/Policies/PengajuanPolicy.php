<?php

namespace App\Policies;

use App\Enums\StatusPengajuan;
use App\Enums\TahapPersetujuan;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Pengguna unit hanya mengakses pengajuan unitnya (BR-21); perubahan hanya saat draf/dikembalikan (BR-03).
 */
class PengajuanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengajuan.lihat');
    }

    public function view(User $user, Pengajuan $pengajuan): bool
    {
        return $user->can('pengajuan.lihat') && $pengajuan->terlihatOleh($user);
    }

    public function create(User $user): bool
    {
        return $user->can('pengajuan.buat') && $user->unit_id !== null;
    }

    public function update(User $user, Pengajuan $pengajuan): bool
    {
        return $user->can('pengajuan.ubah')
            && $this->unitSama($user, $pengajuan)
            && $pengajuan->status->dapatDiubah();
    }

    /** Hanya draf yang belum bernomor yang boleh dihapus (BR-22). */
    public function delete(User $user, Pengajuan $pengajuan): bool
    {
        return $user->can('pengajuan.hapus')
            && $this->unitSama($user, $pengajuan)
            && $pengajuan->status === StatusPengajuan::Draf
            && $pengajuan->nomor === null;
    }

    /** Tombol "Verifikasi" (tahap kepala unit/keuangan), termasuk pemisahan tugas (BR-06, BR-07). */
    public function verifikasi(User $user, Pengajuan $pengajuan): bool
    {
        $tahap = $pengajuan->tahapBerjalan();

        return in_array($tahap, [TahapPersetujuan::KepalaUnit, TahapPersetujuan::Keuangan], true)
            && $this->bolehBertindak($user, $pengajuan, $tahap);
    }

    /** Tombol "Setujui" (tahap wadek). */
    public function setujui(User $user, Pengajuan $pengajuan): bool
    {
        return $pengajuan->tahapBerjalan() === TahapPersetujuan::Wadek
            && $this->bolehBertindak($user, $pengajuan, TahapPersetujuan::Wadek);
    }

    private function bolehBertindak(User $user, Pengajuan $pengajuan, TahapPersetujuan $tahap): bool
    {
        return $pengajuan->pemegangTahap($user, $tahap) && ! $pengajuan->melanggarPemisahanTugas($user, $tahap);
    }

    private function unitSama(User $user, Pengajuan $pengajuan): bool
    {
        return $user->unit_id !== null && $user->unit_id === $pengajuan->unit_id;
    }
}
