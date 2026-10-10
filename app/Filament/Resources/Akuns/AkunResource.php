<?php

namespace App\Filament\Resources\Akuns;

use App\Filament\Resources\Akuns\Pages\CreateAkun;
use App\Filament\Resources\Akuns\Pages\EditAkun;
use App\Filament\Resources\Akuns\Pages\ListAkuns;
use App\Filament\Resources\Akuns\Schemas\AkunForm;
use App\Filament\Resources\Akuns\Tables\AkunsTable;
use App\Models\Akun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AkunResource extends Resource
{
    protected static ?string $model = Akun::class;

    protected static ?string $slug = 'akun';

    protected static ?string $modelLabel = 'Akun';

    protected static ?string $pluralModelLabel = 'Akun BAS';

    protected static ?string $navigationLabel = 'Akun BAS';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return AkunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AkunsTable::configure($table);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAkuns::route('/'),
            'create' => CreateAkun::route('/create'),
            'edit' => EditAkun::route('/{record}/edit'),
        ];
    }
}
