<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusEkspor: string implements HasColor, HasLabel
{
    case Antre = 'antre';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Gagal = 'gagal';
    case Kedaluwarsa = 'kedaluwarsa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Antre => 'Antre',
            self::Diproses => 'Diproses',
            self::Selesai => 'Selesai',
            self::Gagal => 'Gagal',
            self::Kedaluwarsa => 'Kedaluwarsa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Antre, self::Diproses => 'info',
            self::Selesai => 'success',
            self::Gagal => 'danger',
            self::Kedaluwarsa => 'gray',
        };
    }

    public function bolehBerpindahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Antre => [self::Diproses, self::Gagal],
            self::Diproses => [self::Selesai, self::Gagal],
            self::Selesai => [self::Kedaluwarsa],
            self::Gagal, self::Kedaluwarsa => [],
        }, true);
    }
}
