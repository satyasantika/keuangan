<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusTahunAnggaran: string implements HasColor, HasLabel
{
    case Perencanaan = 'perencanaan';
    case Berjalan = 'berjalan';
    case Ditutup = 'ditutup';

    public function getLabel(): string
    {
        return match ($this) {
            self::Perencanaan => 'Perencanaan',
            self::Berjalan => 'Berjalan',
            self::Ditutup => 'Ditutup',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Perencanaan => 'gray',
            self::Berjalan => 'success',
            self::Ditutup => 'danger',
        };
    }

    /**
     * Satu arah: perencanaan → berjalan → ditutup (BR-20).
     */
    public function bolehBerpindahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Perencanaan => $tujuan === self::Berjalan,
            self::Berjalan => $tujuan === self::Ditutup,
            self::Ditutup => false,
        };
    }
}
