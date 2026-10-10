<?php

namespace App\Filament\Resources\Akuns\Pages;

use App\Filament\Imports\AkunImporter;
use App\Filament\Resources\Akuns\AkunResource;
use App\Models\Akun;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListAkuns extends ListRecords
{
    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Impor akun')
                ->importer(AkunImporter::class)
                ->authorize('import', Akun::class)
                // Satu-satunya input berkas (STANDAR-TEKNIS §1a): berkas sementara dihapus setelah dibaca.
                ->after(function (array $data): void {
                    if (($data['file'] ?? null) instanceof TemporaryUploadedFile) {
                        $data['file']->delete();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
