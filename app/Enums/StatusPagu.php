<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPagu: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Ditetapkan = 'ditetapkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Ditetapkan => 'Ditetapkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Ditetapkan => 'success',
        };
    }

    /**
     * Draf → ditetapkan; perubahan jumlah pagu ditetapkan dicatat sebagai ditetapkan → ditetapkan.
     */
    public function bolehBerpindahKe(self $tujuan): bool
    {
        return $tujuan === self::Ditetapkan;
    }
}
