<?php

namespace App\Filament\Resources\JenisPencairans\Pages;

use App\Filament\Resources\JenisPencairans\JenisPencairanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJenisPencairans extends ManageRecords
{
    protected static string $resource = JenisPencairanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
