<?php

namespace App\Filament\Resources\Pengembalians\Pages;

use App\Filament\Resources\Pengembalians\Actions\AksiPengembalian;
use App\Filament\Resources\Pengembalians\PengembalianResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPengembalian extends ViewRecord
{
    protected static string $resource = PengembalianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiPengembalian::verifikasi(),
            AksiPengembalian::tolak(),
        ];
    }
}
