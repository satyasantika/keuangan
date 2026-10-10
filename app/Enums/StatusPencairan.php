<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPencairan: string implements HasColor, HasLabel
{
    case Dicatat = 'dicatat';
    case Dibatalkan = 'dibatalkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dicatat => 'Dicatat',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dicatat => 'success',
            self::Dibatalkan => 'danger',
        };
    }

    public function bolehBerpindahKe(self $tujuan): bool
    {
        return $this === self::Dicatat && $tujuan === self::Dibatalkan;
    }
}
