<?php

namespace App\Filament\Resources\TahunAnggarans\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Baca-saja; aksi kunci/buka kunci dibuat di F9.
 */
class PeriodeRelationManager extends RelationManager
{
    protected static string $relationship = 'periode';

    protected static ?string $title = 'Periode';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->paginated(false)
            ->columns([
                TextColumn::make('bulan')->label('Bulan ke-'),
                TextColumn::make('nama')->label('Periode'),
                TextColumn::make('tanggal_mulai')->label('Mulai')->date('d M Y'),
                TextColumn::make('tanggal_selesai')->label('Selesai')->date('d M Y'),
                TextColumn::make('status')->label('Status')->badge(),
            ]);
    }
}
