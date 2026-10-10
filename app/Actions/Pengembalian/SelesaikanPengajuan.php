<?php

namespace App\Actions\Pengembalian;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\Uang;

/**
 * Menyelesaikan pengajuan bila SPJ diterima dan sisa dana nol (BR-18). Dipanggil di dalam transaksi.
 */
class SelesaikanPengajuan
{
    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Pengajuan $pengajuan, ?User $oleh = null): bool
    {
        $pengajuan->refresh();

        if ($pengajuan->status !== StatusPengajuan::Dicairkan || ! $pengajuan->spjSudahDiterima()) {
            return false;
        }

        if (Uang::bandingkan($pengajuan->sisaDana(), '0') !== 0) {
            return false;
        }

        $pengajuan->forceFill(['status' => StatusPengajuan::Selesai, 'selesai_pada' => now()])->save();
        $this->catatRiwayat->handle($pengajuan, StatusPengajuan::Dicairkan, StatusPengajuan::Selesai, $oleh, 'Selesai otomatis');

        return true;
    }
}
