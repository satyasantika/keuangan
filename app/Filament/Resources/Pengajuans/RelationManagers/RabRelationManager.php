<?php

namespace App\Filament\Resources\Pengajuans\RelationManagers;

use App\Filament\Resources\RevisiRabs\Actions\AksiRevisi;
use App\Models\Rab;
use App\Support\Uang;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Versi RAB pengajuan (0 = awal, 1, 2, … = revisi) beserta alur revisi.
 */
class RabRelationManager extends RelationManager
{
    protected static string $relationship = 'rab';

    protected static ?string $title = 'Versi RAB';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pengajuan'))
            ->defaultSort('nomor_revisi')
            ->columns([
                TextColumn::make('nomor_revisi')->label('Versi')->formatStateUsing(fn (int $state): string => $state === 0 ? 'Awal' : "Revisi ke-{$state}"),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('total')->label('Total')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('alasan_revisi')->label('Alasan')->wrap()->placeholder('—'),
                TextColumn::make('disetujui_pada')->label('Disetujui')->date('d M Y')->placeholder('—'),
                TextColumn::make('aktif')->label('')->badge()->color('success')
                    ->state(fn (Rab $record): ?string => $record->id === $this->getOwnerRecord()->getAttribute('rab_aktif_id') ? 'Aktif' : null),
            ])
            ->headerActions([
                AksiRevisi::buat()->record(fn () => $this->getOwnerRecord()),
            ])
            ->recordActions([
                AksiRevisi::bandingkan(),
                AksiRevisi::editItem(),
                AksiRevisi::ajukan(),
                ...AksiRevisi::keputusan(),
            ]);
    }
}
