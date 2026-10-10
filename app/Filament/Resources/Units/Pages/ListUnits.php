<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Imports\UnitImporter;
use App\Filament\Resources\Units\UnitResource;
use App\Models\Unit;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListUnits extends ListRecords
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Impor unit')
                ->importer(UnitImporter::class)
                ->authorize('import', Unit::class)
                // Berkas impor hanya sementara (STANDAR-TEKNIS §1a): baris sudah dibaca ke job, berkas dihapus.
                ->after(function (array $data): void {
                    if (($data['file'] ?? null) instanceof TemporaryUploadedFile) {
                        $data['file']->delete();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
