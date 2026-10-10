<?php

namespace App\Filament\Resources\Pagus;

use App\Filament\Resources\Pagus\Pages\CreatePagu;
use App\Filament\Resources\Pagus\Pages\EditPagu;
use App\Filament\Resources\Pagus\Pages\ListPagus;
use App\Filament\Resources\Pagus\Pages\ViewPagu;
use App\Filament\Resources\Pagus\RelationManagers\PaguAkunRelationManager;
use App\Filament\Resources\Pagus\Schemas\PaguForm;
use App\Filament\Resources\Pagus\Tables\PagusTable;
use App\Models\Pagu;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaguResource extends Resource
{
    protected static ?string $model = Pagu::class;

    protected static ?string $slug = 'pagu';

    protected static ?string $modelLabel = 'Pagu';

    protected static ?string $pluralModelLabel = 'Pagu';

    protected static ?string $navigationLabel = 'Pagu Unit';

    protected static string|UnitEnum|null $navigationGroup = 'Anggaran';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    public static function form(Schema $schema): Schema
    {
        return PaguForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagusTable::configure($table);
    }

    /**
     * Daftar dibatasi unit pengguna (BR-21).
     *
     * @return Builder<Pagu>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        /** @var Builder<Pagu> $query */
        $query = parent::getEloquentQuery();

        return $query->terlihatOleh($pengguna);
    }

    /**
     * Pengikatan URL tidak dibatasi agar akses lintas unit ditolak Policy (403), bukan 404.
     *
     * @return Builder<Pagu>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<Pagu> $query */
        $query = parent::getEloquentQuery();

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            PaguAkunRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPagus::route('/'),
            'create' => CreatePagu::route('/create'),
            'view' => ViewPagu::route('/{record}'),
            'edit' => EditPagu::route('/{record}/edit'),
        ];
    }
}
