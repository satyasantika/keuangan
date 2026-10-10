<?php

namespace App\Filament\Pages\Auth;

use App\Actions\Pengguna\PerbaruiProfil;
use App\Models\User;
use App\Support\Impersonasi;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * Profil sendiri: nama, NIP, no. HP, dan kata sandi. Surel hanya dapat diubah oleh super-admin lewat menu Pengguna.
 */
class EditProfil extends EditProfile
{
    public function mount(): void
    {
        // Profil dan kata sandi pengguna yang disamarkan tidak boleh diubah oleh super-admin.
        abort_if(Impersonasi::aktif(), 403, 'Profil tidak dapat diubah saat impersonasi.');

        parent::mount();

        $pengguna = $this->getUser();

        if ($pengguna instanceof User && $pengguna->wajib_ganti_sandi) {
            Notification::make()
                ->title('Ganti kata sandi Anda')
                ->body('Administrator mewajibkan Anda mengganti kata sandi sebelum melanjutkan.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')
                ->columns(2)
                ->schema([
                    $this->getNameFormComponent(),
                    TextInput::make('email')
                        ->label('Surel')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Surel adalah identitas masuk; perubahan lewat super-admin.'),
                    TextInput::make('nip')->label('NIP/NIDN')->maxLength(30),
                    TextInput::make('no_hp')->label('No. HP')->tel()->maxLength(20),
                ]),
            Section::make('Ganti kata sandi')
                ->description('Setelah kata sandi diganti, perangkat lain yang sedang masuk dengan akun ini otomatis keluar.')
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        return app(PerbaruiProfil::class)->handle($record, $data);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $kolom */
        $kolom = parent::getPasswordFormComponent();

        return $kolom
            ->required(fn (): bool => (bool) $this->getUser()->getAttributeValue('wajib_ganti_sandi'))
            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && Hash::check($value, (string) $this->getUser()->getAuthPassword())) {
                    $fail('Kata sandi baru harus berbeda dari yang sekarang.');
                }
            });
    }
}
