<?php

namespace App\Actions\Persetujuan;

use App\Enums\TahapPersetujuan;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Pemeriksaan bersama aksi persetujuan: pemegang tahap dan pemisahan tugas (BR-06, BR-07).
 */
trait MemeriksaPemisahanTugas
{
    public function pastikanBolehBertindak(Pengajuan $pengajuan, User $pengguna, ?TahapPersetujuan $tahap = null): TahapPersetujuan
    {
        $tahap ??= $pengajuan->tahapBerjalan();

        if ($tahap === null) {
            throw new TransisiStatusTidakSahException('Pengajuan tidak sedang menunggu persetujuan.');
        }

        if (! $pengajuan->pemegangTahap($pengguna, $tahap)) {
            throw new AuthorizationException("Anda tidak berhak bertindak pada tahap {$tahap->getLabel()} untuk pengajuan ini.");
        }

        if ($pengajuan->melanggarPemisahanTugas($pengguna, $tahap)) {
            throw new PelanggaranPemisahanTugasException('Pemisahan tugas: pembuat pengajuan atau pengguna yang sudah bertindak di tahap lain tidak boleh bertindak di tahap ini.');
        }

        return $tahap;
    }

    /**
     * Status setelah lock harus sama dengan yang dilihat pengguna (BR-09).
     */
    protected function pastikanBelumDiproses(Pengajuan $dilihat, Pengajuan $terkini): void
    {
        if ($dilihat->status !== $terkini->status) {
            throw new TransisiStatusTidakSahException('Pengajuan sudah diproses pengguna lain.');
        }
    }
}
