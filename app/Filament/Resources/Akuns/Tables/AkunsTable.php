<?php

namespace App\Filament\Resources\Akuns\Tables;

use App\Models\Akun;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AkunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->wrap(),
                TextColumn::make('kelompok')->label('Kelompok')->sortable(),
                TextColumn::make('jenis_belanja')
                    ->label('Jenis belanja')
                    ->formatStateUsing(fn (?string $state): ?string => Akun::JENIS_BELANJA[$state] ?? $state)
                    ->toggleable(),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'contoh' ? 'CONTOH' : (Akun::SUMBER[$state] ?? $state))
                    ->color(fn (string $state): string => $state === 'contoh' ? 'danger' : 'gray')
                    ->tooltip(fn (Akun $record): ?string => $record->contoh() ? 'Data contoh — perlu verifikasi terhadap BAS yang berlaku' : null),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Aktif'),
                SelectFilter::make('kelompok')
                    ->label('Kelompok')
                    ->options(fn (): array => Akun::query()->whereNotNull('kelompok')->distinct()->orderBy('kelompok')->pluck('kelompok', 'kelompok')->all()),
                SelectFilter::make('sumber')->label('Sumber')->options(Akun::SUMBER),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
