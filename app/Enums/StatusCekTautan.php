<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusCekTautan: string implements HasColor, HasLabel
{
    case Belum = 'belum';
    case DapatDiakses = 'dapat_diakses';
    case TidakDapatDiakses = 'tidak_dapat_diakses';

    public function getLabel(): string
    {
        return match ($this) {
            self::Belum => 'Belum dicek',
            self::DapatDiakses => 'Dapat diakses',
            self::TidakDapatDiakses => 'Tidak dapat diakses',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Belum => 'gray',
            self::DapatDiakses => 'success',
            self::TidakDapatDiakses => 'danger',
        };
    }
}
