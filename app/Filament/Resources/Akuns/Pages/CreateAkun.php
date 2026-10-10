<?php

namespace App\Filament\Resources\Akuns\Pages;

use App\Filament\Resources\Akuns\AkunResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAkun extends CreateRecord
{
    protected static string $resource = AkunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sumber'] = 'manual';

        return $data;
    }
}
