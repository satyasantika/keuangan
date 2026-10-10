<?php

namespace App\Filament\Resources\RekapHonorEksternals\Pages;

use App\Filament\Resources\RekapHonorEksternals\RekapHonorEksternalResource;
use App\Jobs\ImporRekapHonor;
use App\Models\RekapHonorEksternal;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListRekapHonorEksternals extends ListRecords
{
    protected static string $resource = RekapHonorEksternalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imporRekapHonor')
                ->label('Impor rekap honor')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->authorize(fn (): bool => auth()->user()?->can('impor', RekapHonorEksternal::class) ?? false)
                ->modalDescription('Berkas Excel ekspor fkipapp (rekap total per bulan × prodi × jenis ujian). Berkas dihapus setelah diproses.')
                ->schema([
                    // Satu-satunya unggahan yang diizinkan (STANDAR-TEKNIS §1a): impor Excel yang dihapus setelah diproses.
                    FileUpload::make('berkas')
                        ->label('Berkas .xlsx')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']) // xlsx = arsip zip; isi divalidasi pembaca Excel
                        ->maxSize(10240),
                ])
                ->action(function (array $data): void {
                    /** @var TemporaryUploadedFile $berkas */
                    $berkas = $data['berkas'];
                    $folder = storage_path('app/tmp/impor');
                    File::ensureDirectoryExists($folder);
                    $jalur = $folder.'/'.Str::uuid7().'.xlsx';
                    File::copy($berkas->getRealPath(), $jalur);
                    $berkas->delete();

                    /** @var User $pengguna */
                    $pengguna = auth()->user();
                    ImporRekapHonor::dispatch($jalur, Str::limit(basename($berkas->getClientOriginalName()), 200, ''), $pengguna);

                    Notification::make()->success()->title('Berkas diterima dan sedang diproses.')->body('Hasil impor dikirim lewat notifikasi.')->send();
                }),
        ];
    }
}
