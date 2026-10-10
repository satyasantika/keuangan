<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Pengguna\ImporPenggunaTempel;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imporTempel')
                ->label('Impor massal (tempel)')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('create', User::class))
                ->modalHeading('Impor pengguna dari hasil salin-tempel')
                ->modalDescription('Salin baris dari Excel/Google Sheets lalu tempel. Kolom berurutan: nama, surel, peran, kode unit, NIP, no. HP. Peran: super-admin, admin-keuangan, bpp, wadek-keuangan, kepala-unit, admin-unit, pimpinan. Kode unit wajib untuk kepala-unit dan admin-unit. Satu baris salah membatalkan seluruh impor.')
                ->modalSubmitActionLabel('Impor')
                ->schema([
                    Textarea::make('teks')->label('Data pengguna')->rows(10)->required()
                        ->placeholder("Nama\tsurel@unsil.ac.id\tadmin-unit\tPMAT\t198001012005011001\t081234567890"),
                    TextInput::make('kata_sandi')->label('Kata sandi awal')->password()->revealable()->required()->minLength(8)
                        ->helperText('Dipakai untuk semua akun baru dalam impor ini.'),
                    Toggle::make('wajib_ganti_sandi')->label('Wajib ganti kata sandi saat masuk pertama')->default(true),
                ])
                ->action(function (array $data): void {
                    $hasil = app(ImporPenggunaTempel::class)->handle((string) $data['teks'], (string) $data['kata_sandi'], (bool) $data['wajib_ganti_sandi'], auth()->user());

                    if ($hasil['galat'] !== []) {
                        Notification::make()
                            ->title('Impor dibatalkan: tidak ada pengguna yang dibuat')
                            ->body(implode("\n", array_slice($hasil['galat'], 0, 15)).(count($hasil['galat']) > 15 ? "\n…dan ".(count($hasil['galat']) - 15).' galat lain.' : ''))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()->title("{$hasil['dibuat']} pengguna dibuat")->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
