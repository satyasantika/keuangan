<?php

namespace App\Filament\Resources\Pagus\Pages;

use App\Filament\Resources\Pagus\PaguResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePagu extends CreateRecord
{
    protected static string $resource = PaguResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['dibuat_oleh'] = auth()->id();

        return $data;
    }
}
