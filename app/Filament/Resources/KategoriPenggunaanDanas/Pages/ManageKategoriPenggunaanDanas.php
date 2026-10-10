<?php

namespace App\Filament\Resources\KategoriPenggunaanDanas\Pages;

use App\Filament\Resources\KategoriPenggunaanDanas\KategoriPenggunaanDanaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKategoriPenggunaanDanas extends ManageRecords
{
    protected static string $resource = KategoriPenggunaanDanaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
