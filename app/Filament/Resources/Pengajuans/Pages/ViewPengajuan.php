<?php

namespace App\Filament\Resources\Pengajuans\Pages;

use App\Filament\Resources\Pengajuans\Actions\AksiPengajuan;
use App\Filament\Resources\Pengajuans\PengajuanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPengajuan extends ViewRecord
{
    protected static string $resource = PengajuanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiPengajuan::ajukan(),
            EditAction::make(),
            AksiPengajuan::batalkan(),
        ];
    }
}
