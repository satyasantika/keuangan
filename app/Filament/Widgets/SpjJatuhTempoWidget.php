<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Pengajuans\PengajuanResource;
use App\Filament\Widgets\Concerns\MemakaiParameterDasbor;
use App\Models\Pengajuan;
use App\Models\User;
use App\Queries\SpjTerlambatQuery;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Pengajuan dengan batas SPJ ≤ 7 hari lagi atau terlambat (lingkup pengguna).
 */
class SpjJatuhTempoWidget extends TableWidget
{
    use MemakaiParameterDasbor;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'SPJ jatuh tempo / terlambat';

    public static function canView(): bool
    {
        return auth()->user()?->can('dasbor.lihat') ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $table
            ->query(fn () => SpjTerlambatQuery::query($pengguna, $this->parameterLaporan()?->tahunAnggaranId))
            ->recordUrl(fn (Pengajuan $record): string => PengajuanResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Tidak ada SPJ jatuh tempo')
            ->columns([
                TextColumn::make('unit.kode')->label('Unit'),
                TextColumn::make('nomor')->label('Nomor'),
                TextColumn::make('judul_kegiatan')->label('Kegiatan')->limit(40),
                TextColumn::make('batas_spj')->label('Batas SPJ')->date('d M Y')
                    ->color(fn (Pengajuan $record): string => $record->batas_spj?->isPast() ? 'danger' : 'warning'),
            ]);
    }
}
