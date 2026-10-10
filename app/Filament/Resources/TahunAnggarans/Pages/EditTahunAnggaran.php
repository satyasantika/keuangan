<?php

namespace App\Filament\Resources\TahunAnggarans\Pages;

use App\Filament\Resources\TahunAnggarans\TahunAnggaranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTahunAnggaran extends EditRecord
{
    protected static string $resource = TahunAnggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
