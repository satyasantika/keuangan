<?php

namespace App\Filament\Resources\LaporanEkspors;

use App\Filament\Resources\LaporanEkspors\Pages\ListLaporanEkspors;
use App\Models\LaporanEkspor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use UnitEnum;

/**
 * Riwayat ekspor milik pengguna; unduh ulang selama belum kedaluwarsa.
 */
class LaporanEksporResource extends Resource
{
    protected static ?string $model = LaporanEkspor::class;

    protected static ?string $slug = 'riwayat-ekspor';

    protected static ?string $modelLabel = 'Ekspor';

    protected static ?string $pluralModelLabel = 'Riwayat Ekspor';

    protected static ?string $navigationLabel = 'Riwayat Ekspor';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    /**
     * @return Builder<LaporanEkspor>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<LaporanEkspor> $query */
        $query = parent::getEloquentQuery()->where('diminta_oleh', auth()->id());

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('jenis')->label('Laporan'),
                TextColumn::make('format')->label('Format')->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('ukuran_byte')->label('Ukuran')->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Number::fileSize($state)),
                TextColumn::make('created_at')->label('Diminta')->dateTime('d M Y H:i'),
                TextColumn::make('kedaluwarsa_pada')->label('Kedaluwarsa')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('unduh')
                    ->label('Unduh')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (LaporanEkspor $record): bool => $record->dapatDiunduh())
                    ->url(fn (LaporanEkspor $record): string => URL::temporarySignedRoute('ekspor.unduh', $record->kedaluwarsa_pada ?? now()->addHour(), ['laporanEkspor' => $record->id])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanEkspors::route('/'),
        ];
    }
}
