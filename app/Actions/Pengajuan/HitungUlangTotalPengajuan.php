<?php

namespace App\Actions\Pengajuan;

use App\Enums\StatusPencairan;
use App\Models\Pengajuan;
use App\Support\Uang;
use App\Support\VersiCacheDasbor;

/**
 * Menghitung ulang kolom total_* denormalisasi pengajuan dari tabel sumber (BR-26).
 */
class HitungUlangTotalPengajuan
{
    public function handle(Pengajuan $pengajuan): Pengajuan
    {
        $pengajuan->forceFill($this->hitung($pengajuan))->save();

        VersiCacheDasbor::naikkan();

        return $pengajuan;
    }

    /**
     * Nilai total hasil perhitungan ulang tanpa menyimpan (dipakai juga oleh rekonsiliasi).
     *
     * @return array<string, string>
     */
    public function hitung(Pengajuan $pengajuan): array
    {
        return [
            'total_dicairkan' => Uang::jumlahkan(
                $pengajuan->pencairan()
                    ->where('status', StatusPencairan::Dicatat->value)
                    ->pluck('jumlah')
                    ->map(fn ($j): string => (string) $j)
                    ->all(),
            ),
        ];
    }
}
