<?php

namespace App\Notifications;

class RekapMingguan extends NotifikasiKeuangan
{
    /**
     * @param  array{menunggu: array<string, int>, spj_terlambat: array<string, int>, pengembalian_menunggu: int}  $ringkasan
     */
    public function __construct(public array $ringkasan)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return 'Rekap mingguan Keuangan FKIP';
    }

    public function isi(): string
    {
        return self::teks($this->ringkasan);
    }

    public function url(): ?string
    {
        return null;
    }

    /**
     * @param  array{menunggu: array<string, int>, spj_terlambat: array<string, int>, pengembalian_menunggu: int}  $r
     */
    public static function teks(array $r): string
    {
        $menunggu = collect($r['menunggu'])->map(fn (int $n, string $tahap) => "{$tahap}: {$n}")->implode(', ');
        $terlambat = collect($r['spj_terlambat'])->map(fn (int $n, string $unit) => "{$unit}: {$n}")->implode(', ');

        return 'Menunggu persetujuan — '.($menunggu ?: '-').'. SPJ terlambat — '.($terlambat ?: 'tidak ada').'. Pengembalian menunggu verifikasi: '.$r['pengembalian_menunggu'].'.';
    }
}
