<?php

namespace App\Filament\Resources\TahunAnggarans\Pages;

use App\Actions\Master\BuatTahunAnggaran;
use App\Filament\Resources\TahunAnggarans\TahunAnggaranResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTahunAnggaran extends CreateRecord
{
    protected static string $resource = TahunAnggaranResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BuatTahunAnggaran::class)->handle((int) $data['tahun'], auth()->user(), $data['keterangan'] ?? null);
    }
}
