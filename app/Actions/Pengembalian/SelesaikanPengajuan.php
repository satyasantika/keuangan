<?php

namespace App\Actions\Pengembalian;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\Uang;

/**
 * Pengajuan selesai bila SPJ diterima dan (sisa dana nol atau sisa sudah dikembalikan terverifikasi) (BR-18).
 * Kurang bayar (sisa < 0) tidak diselesaikan sampai pencairan tambahan dicatat. Dipanggil di dalam transaksi.
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

        $sisa = $pengajuan->sisaDana();
        $lunas = Uang::bandingkan($sisa, '0') === 0
            || (Uang::bandingkan($sisa, '0') > 0 && Uang::bandingkan((string) $pengajuan->total_dikembalikan, $sisa) === 0);

        if (! $lunas) {
            return false;
        }

        $pengajuan->forceFill(['status' => StatusPengajuan::Selesai, 'selesai_pada' => now()])->save();
        $this->catatRiwayat->handle($pengajuan, StatusPengajuan::Dicairkan, StatusPengajuan::Selesai, $oleh, 'Selesai otomatis');

        return true;
    }
}
