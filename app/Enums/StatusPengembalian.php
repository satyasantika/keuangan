<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPengembalian: string implements HasColor, HasLabel
{
    case Diajukan = 'diajukan';
    case Diverifikasi = 'diverifikasi';
    case Ditolak = 'ditolak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Diverifikasi => 'Diverifikasi',
            self::Ditolak => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Diajukan => 'info',
            self::Diverifikasi => 'success',
            self::Ditolak => 'danger',
        };
    }

    /** PRD §6.3. */
    public function bolehBerpindahKe(self $tujuan): bool
    {
        return $this === self::Diajukan && in_array($tujuan, [self::Diverifikasi, self::Ditolak], true);
    }
}
