<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PenyediaTautan: string implements HasLabel
{
    case GoogleDrive = 'google_drive';
    case GoogleDocs = 'google_docs';
    case Onedrive = 'onedrive';
    case Unsil = 'unsil';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::GoogleDrive => 'Google Drive',
            self::GoogleDocs => 'Google Docs',
            self::Onedrive => 'OneDrive',
            self::Unsil => 'unsil.ac.id',
            self::Lainnya => 'Lainnya',
        };
    }
}
