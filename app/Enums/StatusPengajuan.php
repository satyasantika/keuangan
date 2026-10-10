<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPengajuan: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case DiverifikasiUnit = 'diverifikasi_unit';
    case DiverifikasiKeuangan = 'diverifikasi_keuangan';
    case Disetujui = 'disetujui';
    case Dikembalikan = 'dikembalikan';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';
    case Dicairkan = 'dicairkan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::DiverifikasiUnit => 'Diverifikasi Kepala Unit',
            self::DiverifikasiKeuangan => 'Diverifikasi Keuangan',
            self::Disetujui => 'Disetujui',
            self::Dikembalikan => 'Dikembalikan untuk Perbaikan',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
            self::Dicairkan => 'Dicairkan',
            self::Selesai => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Diajukan, self::DiverifikasiUnit, self::DiverifikasiKeuangan => 'info',
            self::Disetujui, self::Dicairkan => 'primary',
            self::Dikembalikan => 'warning',
            self::Ditolak, self::Dibatalkan => 'danger',
            self::Selesai => 'success',
        };
    }

    /**
     * Tabel transisi sesuai PRD §6.1 (termasuk lompatan bila tahap kepala unit/wadek nonaktif, BR-06,
     * dan pembatalan oleh admin-keuangan, BR-23).
     */
    public function bolehBerpindahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draf => [self::Diajukan, self::Dibatalkan],
            self::Diajukan => [self::DiverifikasiUnit, self::DiverifikasiKeuangan, self::Disetujui, self::Dikembalikan, self::Ditolak, self::Dibatalkan],
            self::DiverifikasiUnit => [self::DiverifikasiKeuangan, self::Disetujui, self::Dikembalikan, self::Ditolak, self::Dibatalkan],
            self::DiverifikasiKeuangan => [self::Disetujui, self::Dikembalikan, self::Ditolak, self::Dibatalkan],
            self::Dikembalikan => [self::Diajukan, self::Dibatalkan],
            self::Disetujui => [self::Dicairkan, self::Dibatalkan],
            self::Dicairkan => [self::Disetujui, self::Selesai],
            self::Ditolak, self::Dibatalkan, self::Selesai => [],
        }, true);
    }

    /**
     * Data inti pengajuan hanya dapat diubah saat draf/dikembalikan (BR-03).
     */
    public function dapatDiubah(): bool
    {
        return in_array($this, [self::Draf, self::Dikembalikan], true);
    }

    /**
     * Kolom total yang dihitung sebagai komitmen pagu (BR-04); null = tidak mengikat pagu.
     */
    public function menghitungKomitmen(): ?string
    {
        return match ($this) {
            self::Diajukan, self::DiverifikasiUnit, self::DiverifikasiKeuangan => 'total_diajukan',
            self::Disetujui, self::Dicairkan => 'total_disetujui',
            self::Selesai => 'total_realisasi',
            self::Draf, self::Dikembalikan, self::Ditolak, self::Dibatalkan => null,
        };
    }

    /**
     * Status yang sedang menunggu keputusan persetujuan.
     */
    public function dalamProsesPersetujuan(): bool
    {
        return in_array($this, [self::Diajukan, self::DiverifikasiUnit, self::DiverifikasiKeuangan], true);
    }
}
