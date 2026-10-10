<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Enums\JenisUnit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(trim($state))),
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(150),
                Select::make('jenis')
                    ->label('Jenis')
                    ->options(JenisUnit::class)
                    ->default(JenisUnit::Prodi)
                    ->required(),
                TextInput::make('jenjang')
                    ->label('Jenjang')
                    ->maxLength(10)
                    ->placeholder('S1, S2, PPG, …'),
                Select::make('kepala_user_id')
                    ->label('Kepala unit')
                    ->relationship('kepala', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('kode_eksternal')
                    ->label('Kode eksternal')
                    ->maxLength(50),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
