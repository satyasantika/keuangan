<?php

namespace App\Queries;

use App\Models\Rab;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Support\Uang;

/**
 * Perbandingan item revisi terhadap RAB aktif (dihitung server dengan Uang).
 */
class PerbandinganRabQuery
{
    /**
     * @return array{baris: list<array{tanda: string, uraian: string, akun: string, lama: string, baru: string, selisih: string, terealisasi: string}>, total_lama: string, total_baru: string, total_selisih: string}
     */
    public static function bandingkan(Rab $revisi): array
    {
        $baru = RabItem::query()->with('akun')->where('rab_id', $revisi->id)->orderBy('urutan')->get();
        // Pembanding = versi asal item revisi (versi yang digantikan), atau RAB aktif bila belum ada asal.
        $pembandingId = RabItem::query()->whereIn('id', $baru->pluck('asal_rab_item_id')->filter())->value('rab_id')
            ?? $revisi->pengajuan->rab_aktif_id;
        $aktif = RabItem::query()->with('akun')->where('rab_id', $pembandingId)->orderBy('urutan')->get();
        $baris = [];

        // Realisasi menunjuk item RAB aktif (setelah revisi berlaku, item baru).
        $terealisasi = fn (array $itemIds): string => Uang::jumlahkan(
            RealisasiItem::query()->whereIn('rab_item_id', array_filter($itemIds))->pluck('jumlah_bruto')->map(fn ($j): string => (string) $j)->all(),
        );

        foreach ($baru as $item) {
            $asal = $item->asal_rab_item_id === null ? null : $aktif->firstWhere('id', $item->asal_rab_item_id);
            $lama = $asal === null ? '0.00' : (string) $asal->jumlah;
            $selisih = Uang::kurang((string) $item->jumlah, $lama);

            $baris[] = [
                'tanda' => $asal === null ? 'tambah' : (Uang::bandingkan($selisih, '0') === 0 && $asal->uraian === $item->uraian ? 'tetap' : 'ubah'),
                'uraian' => $item->uraian,
                'akun' => $item->akun->kode,
                'lama' => $lama,
                'baru' => (string) $item->jumlah,
                'selisih' => $selisih,
                'terealisasi' => $terealisasi([$asal?->id, $item->id]),
            ];
        }

        foreach ($aktif as $asal) {
            if ($baru->contains('asal_rab_item_id', $asal->id)) {
                continue;
            }

            $baris[] = [
                'tanda' => 'hapus',
                'uraian' => $asal->uraian,
                'akun' => $asal->akun->kode,
                'lama' => (string) $asal->jumlah,
                'baru' => '0.00',
                'selisih' => Uang::kurang('0', (string) $asal->jumlah),
                'terealisasi' => $terealisasi([$asal->id]),
            ];
        }

        $totalLama = Uang::jumlahkan($aktif->pluck('jumlah')->map(fn ($j): string => (string) $j)->all());
        $totalBaru = Uang::jumlahkan($baru->pluck('jumlah')->map(fn ($j): string => (string) $j)->all());

        return [
            'baris' => $baris,
            'total_lama' => $totalLama,
            'total_baru' => $totalBaru,
            'total_selisih' => Uang::kurang($totalBaru, $totalLama),
        ];
    }
}
