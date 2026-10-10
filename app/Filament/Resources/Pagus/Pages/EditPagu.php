<?php

namespace App\Filament\Resources\Pagus\Pages;

use App\Filament\Resources\Pagus\PaguResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPagu extends EditRecord
{
    protected static string $resource = PaguResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
