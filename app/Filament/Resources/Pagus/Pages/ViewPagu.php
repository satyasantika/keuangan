<?php

namespace App\Filament\Resources\Pagus\Pages;

use App\Filament\Resources\Pagus\PaguResource;
use App\Filament\Resources\Pagus\Tables\PagusTable;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPagu extends ViewRecord
{
    protected static string $resource = PaguResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PagusTable::aksiTetapkan()->after(fn () => $this->refreshFormData(['status'])),
            PagusTable::aksiUbahPagu()->after(fn () => $this->refreshFormData(['jumlah'])),
            EditAction::make(),
        ];
    }
}
