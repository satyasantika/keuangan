<?php

namespace App\Actions\Pengajuan;

use App\Enums\StatusPencairan;
use App\Enums\StatusPengembalian;
use App\Models\Pengajuan;
use App\Models\PotonganPajak;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Support\Uang;
use App\Support\VersiCacheDasbor;

/**
 * Menghitung ulang kolom total_* denormalisasi pengajuan & SPJ dari tabel sumber (BR-26).
 */
class HitungUlangTotalPengajuan
{
    public function handle(Pengajuan $pengajuan): Pengajuan
    {
        $pengajuan->forceFill($this->hitung($pengajuan))->save();

        $spj = $pengajuan->spj()->first();

        if ($spj !== null) {
            $spj->forceFill($this->hitungSpj($spj))->save();
        }

        VersiCacheDasbor::naikkan();

        return $pengajuan;
    }

    /**
     * Nilai total pengajuan hasil perhitungan ulang tanpa menyimpan (dipakai juga oleh rekonsiliasi).
     *
     * @return array<string, string>
     */
    public function hitung(Pengajuan $pengajuan): array
    {
        $spj = $pengajuan->spj()->first();
        $totalSpj = $spj === null ? ['total_realisasi' => '0.00', 'total_pajak' => '0.00'] : $this->hitungSpj($spj);

        return [
            'total_dicairkan' => $this->jumlahkan($pengajuan->pencairan()->where('status', StatusPencairan::Dicatat->value)->pluck('jumlah')),
            'total_realisasi' => $totalSpj['total_realisasi'],
            'total_pajak' => $totalSpj['total_pajak'],
            'total_dikembalikan' => $this->jumlahkan($pengajuan->pengembalian()->where('status', StatusPengembalian::Diverifikasi->value)->pluck('jumlah')),
        ];
    }

    /**
     * @return array{total_realisasi: string, total_pajak: string}
     */
    public function hitungSpj(Spj $spj): array
    {
        $realisasiIds = RealisasiItem::query()->where('spj_id', $spj->id)->pluck('id');

        return [
            'total_realisasi' => $this->jumlahkan(RealisasiItem::query()->whereKey($realisasiIds)->pluck('jumlah_bruto')),
            'total_pajak' => $this->jumlahkan(PotonganPajak::query()->whereIn('realisasi_item_id', $realisasiIds)->pluck('nilai')),
        ];
    }

    /**
     * @param  iterable<mixed>  $nilai
     */
    private function jumlahkan(iterable $nilai): string
    {
        $daftar = [];

        foreach ($nilai as $n) {
            $daftar[] = (string) $n;
        }

        return Uang::jumlahkan($daftar);
    }
}
