<?php

namespace App\Filament\Resources\Pencairans\Pages;

use App\Filament\Resources\Pencairans\Actions\AksiPencairan;
use App\Filament\Resources\Pencairans\PencairanResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPencairan extends ViewRecord
{
    protected static string $resource = PencairanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiPencairan::batalkan(),
        ];
    }
}
