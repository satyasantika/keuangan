<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TahapPersetujuan: string implements HasLabel
{
    case KepalaUnit = 'kepala_unit';
    case Keuangan = 'keuangan';
    case Wadek = 'wadek';
    case Bendahara = 'bendahara';

    public function getLabel(): string
    {
        return match ($this) {
            self::KepalaUnit => 'Kepala Unit',
            self::Keuangan => 'Keuangan',
            self::Wadek => 'Wakil Dekan Keuangan',
            self::Bendahara => 'Bendahara',
        };
    }
}
