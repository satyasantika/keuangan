<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusSpj: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case Dikembalikan = 'dikembalikan';
    case Diterima = 'diterima';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::Dikembalikan => 'Dikembalikan',
            self::Diterima => 'Diterima',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Diajukan => 'info',
            self::Dikembalikan => 'warning',
            self::Diterima => 'success',
        };
    }

    /** PRD §6.3. */
    public function bolehBerpindahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draf, self::Dikembalikan => [self::Diajukan],
            self::Diajukan => [self::Dikembalikan, self::Diterima],
            self::Diterima => [],
        }, true);
    }

    /** Realisasi & tautannya hanya berubah saat draf/dikembalikan (BR-17). */
    public function dapatDiubah(): bool
    {
        return in_array($this, [self::Draf, self::Dikembalikan], true);
    }
}
