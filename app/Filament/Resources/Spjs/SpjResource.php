<?php

namespace App\Filament\Resources\Spjs;

use App\Filament\Resources\Spjs\Pages\ListSpjs;
use App\Filament\Resources\Spjs\Pages\ViewSpj;
use App\Filament\Resources\Spjs\RelationManagers\RealisasiItemRelationManager;
use App\Filament\Resources\Spjs\RelationManagers\TautanBekuRelationManager;
use App\Filament\Resources\Spjs\Schemas\SpjInfolist;
use App\Filament\Resources\Spjs\Tables\SpjsTable;
use App\Models\Spj;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SpjResource extends Resource
{
    protected static ?string $model = Spj::class;

    protected static ?string $slug = 'spj';

    protected static ?string $modelLabel = 'SPJ';

    protected static ?string $pluralModelLabel = 'SPJ';

    protected static ?string $navigationLabel = 'SPJ & Realisasi';

    protected static string|UnitEnum|null $navigationGroup = 'Kegiatan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function infolist(Schema $schema): Schema
    {
        return SpjInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpjsTable::configure($table);
    }

    /**
     * @return Builder<Spj>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        /** @var Builder<Spj> $query */
        $query = parent::getEloquentQuery();

        return $pengguna->lingkupFakultas() ? $query : $query->whereHas('pengajuan', fn (Builder $q) => $q->terlihatOleh($pengguna));
    }

    /**
     * @return Builder<Spj>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<Spj> $query */
        $query = parent::getEloquentQuery();

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            RealisasiItemRelationManager::class,
            TautanBekuRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpjs::route('/'),
            'view' => ViewSpj::route('/{record}'),
        ];
    }
}
