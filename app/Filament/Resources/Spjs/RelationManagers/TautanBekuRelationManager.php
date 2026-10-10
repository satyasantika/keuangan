<?php

namespace App\Filament\Resources\Spjs\RelationManagers;

use App\Enums\JenisTautan;
use App\Models\TautanBerkasBeku;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot tautan bukti saat SPJ diterima (BR-17), baca-saja.
 */
class TautanBekuRelationManager extends RelationManager
{
    protected static string $relationship = 'tautanBeku';

    protected static ?string $title = 'Tautan dibekukan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function berubah(TautanBerkasBeku $beku): bool
    {
        $kini = $beku->tautanBerkas()->first();

        return $kini === null || $kini->trashed() || $kini->url !== $beku->url;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tautanBerkas'))
            ->defaultSort('dibekukan_pada')
            ->columns([
                TextColumn::make('pemilik_type')->label('Pemilik')->formatStateUsing(fn (string $state): string => match ($state) {
                    'realisasi_item' => 'Item realisasi',
                    'pencairan' => 'Pencairan',
                    'pengajuan' => 'TOR/pengajuan',
                    'pengembalian' => 'Pengembalian',
                    default => $state,
                }),
                TextColumn::make('jenis')->label('Jenis')->formatStateUsing(fn (string $state): string => JenisTautan::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('label')->label('Label')->wrap(),
                TextColumn::make('status_cek')->label('Status saat beku')->badge(),
                TextColumn::make('dibekukan_pada')->label('Dibekukan')->dateTime('d F Y H:i'),
                TextColumn::make('perubahan')
                    ->label('Audit')
                    ->badge()
                    ->state(fn (TautanBerkasBeku $record): ?string => self::berubah($record) ? 'Berubah setelah dibekukan' : null)
                    ->color('danger'),
            ])
            ->recordActions([
                Action::make('buka')
                    ->label('Buka')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (TautanBerkasBeku $record): string => $record->url)
                    ->extraAttributes(['rel' => 'noopener noreferrer'])
                    ->openUrlInNewTab(),
            ]);
    }
}
