<?php

namespace App\Actions\Revisi;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Rab;
use App\Models\User;
use App\Policies\RabPolicy;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Admin-keuangan memverifikasi revisi; bila tahap wadek nonaktif (snapshot pengajuan) langsung berlaku.
 */
class VerifikasiRevisiRab
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly SetujuiRevisiRab $setujui,
    ) {}

    public function handle(Rab $rab, User $oleh, ?string $catatan = null): Rab
    {
        if (! $oleh->can('verifikasi', $rab)) {
            if ($oleh->can('revisi.setujui') && (new RabPolicy)->melanggarPemisahan($oleh, $rab, TahapPersetujuan::Keuangan)) {
                throw new PelanggaranPemisahanTugasException('Pemisahan tugas: pembuat revisi atau pengguna yang sudah bertindak di tahap lain tidak boleh memverifikasi revisi ini.');
            }

            throw new AuthorizationException('Anda tidak berhak memverifikasi revisi ini.');
        }

        return $this->setujui->dalamKunci($rab, function (Rab $terkini) use ($oleh, $catatan): Rab {
            if ($terkini->status !== StatusRab::Diajukan || ! $oleh->can('verifikasi', $terkini)) {
                throw new TransisiStatusTidakSahException('Revisi sudah diproses pengguna lain.');
            }

            if (! $terkini->pengajuan->wajib_wadek) {
                return $this->setujui->aktifkan($terkini, $oleh, TahapPersetujuan::Keuangan, $catatan);
            }

            $terkini->forceFill(['status' => StatusRab::DiverifikasiKeuangan])->save();
            $this->catatRiwayat->handle($terkini, StatusRab::Diajukan, StatusRab::DiverifikasiKeuangan, $oleh, $catatan, TahapPersetujuan::Keuangan);

            return $terkini;
        });
    }
}
