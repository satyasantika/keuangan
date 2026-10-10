<?php

namespace App\Filament\Resources\TahunAnggarans\Pages;

use App\Filament\Resources\TahunAnggarans\TahunAnggaranResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTahunAnggaran extends ViewRecord
{
    protected static string $resource = TahunAnggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
