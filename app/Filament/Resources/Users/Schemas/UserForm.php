<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Peran;
use App\Models\Role;
use App\Rules\EmailUnsil;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(150),
                TextInput::make('email')
                    ->label('Surel')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->rule(new EmailUnsil),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(10)
                    ->helperText('Kosongkan saat mengubah bila kata sandi tidak diganti.'),
                TextInput::make('nip')
                    ->label('NIP/NIDN')
                    ->maxLength(30),
                TextInput::make('no_hp')
                    ->label('No. HP')
                    ->tel()
                    ->maxLength(20),
                TextInput::make('kode_eksternal')
                    ->label('Kode eksternal')
                    ->maxLength(50),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
                CheckboxList::make('roles')
                    ->label('Peran')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => auth()->user()?->hasRole(Peran::SuperAdmin->value)
                            ? $query
                            : $query->where('name', '!=', Peran::SuperAdmin->value),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Role $record): string => Peran::tryFrom($record->name)?->getLabel() ?? $record->name)
                    ->rules([
                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            $idSuperAdmin = Role::where('name', Peran::SuperAdmin->value)->value('id');

                            if (in_array($idSuperAdmin, (array) $value, true) && ! auth()->user()?->hasRole(Peran::SuperAdmin->value)) {
                                $fail('Hanya super-admin yang boleh memberi peran super-admin.');
                            }
                        },
                    ])
                    ->columns(2),
            ]);
    }
}
