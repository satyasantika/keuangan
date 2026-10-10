<?php

namespace App\Filament\Resources\Pengajuans\RelationManagers;

use App\Enums\StatusPengajuan;
use App\Models\RiwayatStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Linimasa status pengajuan (BR-08), baca-saja.
 */
class RiwayatStatusRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatStatus';

    protected static ?string $title = 'Linimasa status';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $label = fn (?string $state): string => $state === null ? '—' : (StatusPengajuan::tryFrom($state)?->getLabel() ?? $state);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'asc')
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('Sistem'),
                TextColumn::make('tahap')->label('Tahap')->badge()->placeholder('—'),
                TextColumn::make('dari')->label('Dari')->badge()->color('gray')->formatStateUsing($label)->placeholder('—'),
                TextColumn::make('ke')->label('Ke')->badge()
                    ->formatStateUsing($label)
                    ->color(fn (RiwayatStatus $record): string => StatusPengajuan::tryFrom($record->ke)?->getColor() ?? 'gray'),
                TextColumn::make('catatan')->label('Catatan')->wrap()->placeholder('—'),
            ]);
    }
}
