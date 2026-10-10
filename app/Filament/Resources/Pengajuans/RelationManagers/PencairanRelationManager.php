<?php

namespace App\Filament\Resources\Pengajuans\RelationManagers;

use App\Filament\Resources\Pencairans\Actions\AksiPencairan;
use App\Filament\Resources\Pencairans\PencairanResource;
use App\Models\Pencairan;
use App\Support\Uang;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencairan pengajuan: baca-saja untuk unit; BPP dapat membatalkan.
 */
class PencairanRelationManager extends RelationManager
{
    protected static string $relationship = 'pencairan';

    protected static ?string $title = 'Pencairan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $pengguna = auth()->user();

        return $pengguna !== null && $pengguna->can('view', $ownerRecord) && $pengguna->can('pencairan.lihat');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('jenisPencairan'))
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y'),
                TextColumn::make('jenisPencairan.kode')->label('Jenis'),
                TextColumn::make('jumlah')->label('Jumlah')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('nomor_bukti')->label('Nomor bukti'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Pencairan $record): string => PencairanResource::getUrl('view', ['record' => $record])),
                AksiPencairan::batalkan(),
            ]);
    }
}
