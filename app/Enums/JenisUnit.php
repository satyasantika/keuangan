<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisUnit: string implements HasLabel
{
    case Prodi = 'prodi';
    case Laboratorium = 'laboratorium';
    case UnitPendukung = 'unit_pendukung';
    case Pimpinan = 'pimpinan';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prodi => 'Program Studi',
            self::Laboratorium => 'Laboratorium',
            self::UnitPendukung => 'Unit Pendukung',
            self::Pimpinan => 'Pimpinan',
            self::Lainnya => 'Lainnya',
        };
    }
}
