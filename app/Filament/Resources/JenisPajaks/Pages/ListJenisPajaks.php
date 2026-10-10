<?php

namespace App\Filament\Resources\JenisPajaks\Pages;

use App\Filament\Resources\JenisPajaks\JenisPajakResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisPajaks extends ListRecords
{
    protected static string $resource = JenisPajakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
