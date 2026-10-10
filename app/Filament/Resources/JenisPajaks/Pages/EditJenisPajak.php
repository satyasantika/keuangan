<?php

namespace App\Filament\Resources\JenisPajaks\Pages;

use App\Filament\Resources\JenisPajaks\JenisPajakResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisPajak extends EditRecord
{
    protected static string $resource = JenisPajakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
