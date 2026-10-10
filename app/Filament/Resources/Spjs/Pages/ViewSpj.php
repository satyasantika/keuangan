<?php

namespace App\Filament\Resources\Spjs\Pages;

use App\Filament\Resources\Pengembalians\Actions\AksiPengembalian;
use App\Filament\Resources\Spjs\Actions\AksiSpj;
use App\Filament\Resources\Spjs\SpjResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSpj extends ViewRecord
{
    protected static string $resource = SpjResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiSpj::ajukan(),
            AksiSpj::terima(),
            AksiSpj::kembalikan(),
            AksiPengembalian::catat(),
        ];
    }
}
