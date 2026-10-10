<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Peran: string implements HasLabel
{
    case SuperAdmin = 'super-admin';
    case AdminKeuangan = 'admin-keuangan';
    case Bpp = 'bpp';
    case WadekKeuangan = 'wadek-keuangan';
    case KepalaUnit = 'kepala-unit';
    case AdminUnit = 'admin-unit';
    case Pimpinan = 'pimpinan';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Admin TI Fakultas',
            self::AdminKeuangan => 'Admin Keuangan Fakultas',
            self::Bpp => 'Bendahara Pengeluaran Pembantu',
            self::WadekKeuangan => 'Wakil Dekan Bidang Keuangan / PPK',
            self::KepalaUnit => 'Ketua Prodi / Kepala Unit',
            self::AdminUnit => 'Admin Prodi/Unit',
            self::Pimpinan => 'Dekan / Dekanat',
        };
    }

    /**
     * Peran yang bekerja di tingkat fakultas (tidak dibatasi unit).
     *
     * @return list<self>
     */
    public static function fakultas(): array
    {
        return [self::SuperAdmin, self::AdminKeuangan, self::Bpp, self::WadekKeuangan, self::Pimpinan];
    }

    /**
     * Peran yang wajib memakai autentikasi dua langkah.
     *
     * @return list<self>
     */
    public static function wajibMfa(): array
    {
        return [self::SuperAdmin, self::AdminKeuangan, self::Bpp, self::WadekKeuangan];
    }

    /**
     * Peran yang wajib punya unit (users.unit_id).
     *
     * @return list<self>
     */
    public static function wajibUnit(): array
    {
        return [self::KepalaUnit, self::AdminUnit];
    }
}
