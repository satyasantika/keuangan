<?php

namespace App\Filament\Resources\Akuns\Schemas;

use App\Models\Akun;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->regex('/^\d{6}$/')
                    ->validationMessages(['regex' => 'Kode akun harus 6 digit angka.'])
                    ->unique(ignoreRecord: true),
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('kelompok')
                    ->label('Kelompok')
                    ->maxLength(10)
                    ->helperText('Kosongkan untuk memakai 3 digit pertama kode.'),
                Select::make('jenis_belanja')
                    ->label('Jenis belanja')
                    ->options(Akun::JENIS_BELANJA),
                TextInput::make('keterangan')
                    ->label('Keterangan')
                    ->maxLength(255),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
