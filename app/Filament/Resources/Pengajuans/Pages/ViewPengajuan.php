<?php

namespace App\Filament\Resources\Pengajuans\Pages;

use App\Filament\Resources\Pencairans\Actions\AksiPencairan;
use App\Filament\Resources\Pengajuans\Actions\AksiPengajuan;
use App\Filament\Resources\Pengajuans\Actions\AksiPersetujuan;
use App\Filament\Resources\Pengajuans\PengajuanResource;
use App\Filament\Resources\Pengembalians\Actions\AksiPengembalian;
use App\Filament\Resources\Spjs\Actions\AksiSpj;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPengajuan extends ViewRecord
{
    protected static string $resource = PengajuanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiPengajuan::ajukan(),
            ...AksiPersetujuan::semua(),
            AksiPersetujuan::batalkanKeuangan(),
            AksiPencairan::catat(),
            AksiSpj::buka(),
            AksiPengembalian::catat(),
            AksiPengajuan::cetakPdf(),
            EditAction::make(),
            AksiPengajuan::batalkan(),
        ];
    }
}
