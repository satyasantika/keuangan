<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusRab: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case DiverifikasiKeuangan = 'diverifikasi_keuangan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Digantikan = 'digantikan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::DiverifikasiKeuangan => 'Diverifikasi Keuangan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Digantikan => 'Digantikan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Diajukan, self::DiverifikasiKeuangan => 'info',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Digantikan => 'warning',
        };
    }

    /**
     * Tabel transisi sesuai aturan bisnis revisi RAB (BR-10/BR-11). RAB versi 0 mengikuti pengajuan: langsung diajukan → disetujui
     * bila tahap keuangan menjadi akhir, dan kembali ke draf saat pengajuan dikembalikan.
     */
    public function bolehBerpindahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draf => [self::Diajukan],
            self::Diajukan => [self::DiverifikasiKeuangan, self::Disetujui, self::Draf, self::Ditolak],
            self::DiverifikasiKeuangan => [self::Disetujui, self::Draf, self::Ditolak],
            self::Disetujui => [self::Digantikan],
            self::Ditolak, self::Digantikan => [],
        }, true);
    }
}
