<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPeriode: string implements HasColor, HasLabel
{
    case Terbuka = 'terbuka';
    case Terkunci = 'terkunci';

    public function getLabel(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Terkunci => 'Terkunci',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Terbuka => 'success',
            self::Terkunci => 'warning',
        };
    }

    /**
     * Kunci dan buka kunci dapat berulang (BR-19); aturan siapa yang boleh ada di F9.
     */
    public function bolehBerpindahKe(self $tujuan): bool
    {
        return $this !== $tujuan;
    }
}
