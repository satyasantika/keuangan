<?php

namespace App\Filament\Resources\TahunAnggarans\Pages;

use App\Filament\Resources\TahunAnggarans\TahunAnggaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTahunAnggarans extends ListRecords
{
    protected static string $resource = TahunAnggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
