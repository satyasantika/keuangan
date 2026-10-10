<?php

namespace App\Filament\Resources\TahunAnggarans\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TahunAnggaranForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tahun')
                    ->label('Tahun')
                    ->numeric()
                    ->integer()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Periode 1 Januari–31 Desember beserta 12 periode bulanan dibuat otomatis.'),
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
