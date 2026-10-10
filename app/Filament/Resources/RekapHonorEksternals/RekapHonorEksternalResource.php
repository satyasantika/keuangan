<?php

namespace App\Filament\Resources\RekapHonorEksternals;

use App\Actions\Impor\BatalkanBatchHonor;
use App\Filament\Resources\RekapHonorEksternals\Pages\ListRekapHonorEksternals;
use App\Models\RekapHonorEksternal;
use App\Models\User;
use App\Support\Uang;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Rekap total honor ujian dari fkipapp (BR-24): baca-saja, dikelompokkan per batch impor.
 */
class RekapHonorEksternalResource extends Resource
{
    protected static ?string $model = RekapHonorEksternal::class;

    protected static ?string $slug = 'rekap-honor';

    protected static ?string $modelLabel = 'Rekap Honor Ujian';

    protected static ?string $pluralModelLabel = 'Rekap Honor Ujian';

    protected static ?string $navigationLabel = 'Rekap Honor Ujian';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    /**
     * @return Builder<RekapHonorEksternal>
     */
    public static function getEloquentQuery(): Builder
    {
        $pengguna = auth()->user();

        /** @var Builder<RekapHonorEksternal> $query */
        $query = parent::getEloquentQuery()->with(['periode', 'unit'])
            ->when($pengguna instanceof User && ! $pengguna->lingkupFakultas(), fn (Builder $q) => $q->where('unit_id', $pengguna instanceof User ? $pengguna->unit_id : null));

        return $query;
    }

    public static function table(Table $table): Table
    {
        $rupiah = fn ($state): string => Uang::format((string) $state);

        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultGroup(Group::make('batch_impor')->label('Batch')->getTitleFromRecordUsing(
                fn (RekapHonorEksternal $record): string => ($record->nama_berkas_sumber ?? 'Impor').' — '.$record->created_at?->format('d M Y H:i'),
            ))
            ->columns([
                TextColumn::make('periode.nama')->label('Periode'),
                TextColumn::make('unit.kode')->label('Unit')->placeholder('(tidak terpetakan)'),
                TextColumn::make('jenis_ujian')->label('Jenis ujian'),
                TextColumn::make('jumlah_kegiatan')->label('Kegiatan')->alignEnd(),
                TextColumn::make('jumlah_bruto')->label('Bruto')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('jumlah_pajak')->label('Pajak')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('jumlah_neto')->label('Neto')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state): string => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['aktif' => 'Aktif', 'dibatalkan' => 'Dibatalkan'])->default('aktif'),
            ])
            ->recordActions([
                Action::make('batalkanBatch')
                    ->label('Batalkan batch')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Seluruh baris pada batch impor ini akan berstatus dibatalkan dan tidak tampil di dasbor/laporan.')
                    ->authorize('batalkan')
                    ->action(function (RekapHonorEksternal $record): void {
                        /** @var User $pengguna */
                        $pengguna = auth()->user();
                        $jumlah = app(BatalkanBatchHonor::class)->handle($record->batch_impor, $pengguna);
                        Notification::make()->success()->title("{$jumlah} baris dibatalkan.")->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRekapHonorEksternals::route('/'),
        ];
    }
}
