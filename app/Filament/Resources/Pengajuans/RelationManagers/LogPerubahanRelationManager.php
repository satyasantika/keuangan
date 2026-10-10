<?php

namespace App\Filament\Resources\Pengajuans\RelationManagers;

use App\Models\Aktivitas;
use App\Models\Pengajuan;
use App\Models\RabItem;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Log perubahan nilai (activity_log) pengajuan dan item RAB-nya; hanya untuk peran fakultas.
 */
class LogPerubahanRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';

    protected static ?string $title = 'Log perubahan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->lingkupFakultas() && $pengguna->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Pengajuan $pengajuan */
        $pengajuan = $this->getOwnerRecord();

        $itemIds = RabItem::query()->whereIn('rab_id', $pengajuan->rab()->select('id'))->pluck('id');

        return $table
            ->query(fn (): Builder => Aktivitas::query()
                ->with('causer')
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $w) => $w->where('subject_type', 'pengajuan')->where('subject_id', $pengajuan->id))
                    ->orWhere(fn (Builder $w) => $w->where('subject_type', 'rab_item')->whereIn('subject_id', $itemIds))))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                TextColumn::make('causer.name')->label('Pengguna')->placeholder('Sistem'),
                TextColumn::make('subject_type')->label('Objek')->formatStateUsing(fn (string $state): string => $state === 'rab_item' ? 'Item RAB' : 'Pengajuan'),
                TextColumn::make('event')->label('Peristiwa')->badge(),
                TextColumn::make('attribute_changes')
                    ->label('Perubahan')
                    ->wrap()
                    ->formatStateUsing(function (Aktivitas $record): string {
                        $baru = (array) ($record->attribute_changes['attributes'] ?? []);
                        $lama = (array) ($record->attribute_changes['old'] ?? []);

                        return collect($baru)
                            ->map(fn ($nilai, $kolom) => $kolom.': '.json_encode($lama[$kolom] ?? null, JSON_UNESCAPED_UNICODE).' → '.json_encode($nilai, JSON_UNESCAPED_UNICODE))
                            ->implode('; ');
                    }),
            ]);
    }
}
