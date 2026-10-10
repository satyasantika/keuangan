<?php

namespace App\Filament\Resources\Pencairans;

use App\Filament\Resources\Pencairans\Pages\ListPencairans;
use App\Filament\Resources\Pencairans\Pages\ViewPencairan;
use App\Filament\Resources\Pencairans\Schemas\PencairanInfolist;
use App\Filament\Resources\Pencairans\Tables\PencairansTable;
use App\Models\Pencairan;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PencairanResource extends Resource
{
    protected static ?string $model = Pencairan::class;

    protected static ?string $slug = 'pencairan';

    protected static ?string $modelLabel = 'Pencairan';

    protected static ?string $pluralModelLabel = 'Pencairan';

    protected static ?string $navigationLabel = 'Pencairan';

    protected static string|UnitEnum|null $navigationGroup = 'Bendahara';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function infolist(Schema $schema): Schema
    {
        return PencairanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PencairansTable::configure($table);
    }

    /**
     * @return Builder<Pencairan>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        /** @var Builder<Pencairan> $query */
        $query = parent::getEloquentQuery();

        return $pengguna->lingkupFakultas()
            ? $query
            : $query->whereHas('pengajuan', fn (Builder $q) => $q->terlihatOleh($pengguna));
    }

    /**
     * @return Builder<Pencairan>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<Pencairan> $query */
        $query = parent::getEloquentQuery();

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPencairans::route('/'),
            'view' => ViewPencairan::route('/{record}'),
        ];
    }
}
