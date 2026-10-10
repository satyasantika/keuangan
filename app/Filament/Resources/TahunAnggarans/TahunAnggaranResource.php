<?php

namespace App\Filament\Resources\TahunAnggarans;

use App\Filament\Resources\TahunAnggarans\Pages\CreateTahunAnggaran;
use App\Filament\Resources\TahunAnggarans\Pages\EditTahunAnggaran;
use App\Filament\Resources\TahunAnggarans\Pages\ListTahunAnggarans;
use App\Filament\Resources\TahunAnggarans\Pages\ViewTahunAnggaran;
use App\Filament\Resources\TahunAnggarans\RelationManagers\PeriodeRelationManager;
use App\Filament\Resources\TahunAnggarans\Schemas\TahunAnggaranForm;
use App\Filament\Resources\TahunAnggarans\Tables\TahunAnggaransTable;
use App\Models\TahunAnggaran;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TahunAnggaranResource extends Resource
{
    protected static ?string $model = TahunAnggaran::class;

    protected static ?string $slug = 'tahun-anggaran';

    protected static ?string $modelLabel = 'Tahun Anggaran';

    protected static ?string $pluralModelLabel = 'Tahun Anggaran';

    protected static ?string $navigationLabel = 'Tahun Anggaran';

    protected static string|UnitEnum|null $navigationGroup = 'Anggaran';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return TahunAnggaranForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TahunAnggaransTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PeriodeRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahunAnggarans::route('/'),
            'create' => CreateTahunAnggaran::route('/create'),
            'view' => ViewTahunAnggaran::route('/{record}'),
            'edit' => EditTahunAnggaran::route('/{record}/edit'),
        ];
    }
}
