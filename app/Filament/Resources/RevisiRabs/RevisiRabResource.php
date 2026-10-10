<?php

namespace App\Filament\Resources\RevisiRabs;

use App\Enums\StatusRab;
use App\Filament\Resources\RevisiRabs\Actions\AksiRevisi;
use App\Filament\Resources\RevisiRabs\Pages\ListRevisiRabs;
use App\Models\Rab;
use App\Models\User;
use App\Support\Uang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Daftar revisi RAB lintas pengajuan untuk verifikator (admin-keuangan & wadek).
 */
class RevisiRabResource extends Resource
{
    protected static ?string $model = Rab::class;

    protected static ?string $slug = 'revisi-rab';

    protected static ?string $modelLabel = 'Revisi RAB';

    protected static ?string $pluralModelLabel = 'Revisi RAB';

    protected static ?string $navigationLabel = 'Revisi RAB';

    protected static string|UnitEnum|null $navigationGroup = 'Kegiatan';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pengajuan.unit'))
            ->defaultSort('diajukan_pada', 'desc')
            ->columns([
                TextColumn::make('pengajuan.nomor')->label('Nomor pengajuan')->searchable(),
                TextColumn::make('pengajuan.unit.kode')->label('Unit'),
                TextColumn::make('nomor_revisi')->label('Revisi ke-'),
                TextColumn::make('total')->label('Total')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('diajukan_pada')->label('Diajukan')->since()->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(StatusRab::class)])
            ->recordActions([
                AksiRevisi::bandingkan(),
                ...AksiRevisi::keputusan(),
            ]);
    }

    /**
     * @return Builder<Rab>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        /** @var Builder<Rab> $query */
        $query = parent::getEloquentQuery()->where('nomor_revisi', '>', 0);

        return $pengguna->lingkupFakultas() ? $query : $query->whereHas('pengajuan', fn (Builder $q) => $q->terlihatOleh($pengguna));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRevisiRabs::route('/'),
        ];
    }
}
