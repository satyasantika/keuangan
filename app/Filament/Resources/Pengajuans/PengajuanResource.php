<?php

namespace App\Filament\Resources\Pengajuans;

use App\Filament\Resources\Pengajuans\Pages\CreatePengajuan;
use App\Filament\Resources\Pengajuans\Pages\EditPengajuan;
use App\Filament\Resources\Pengajuans\Pages\ListPengajuans;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Filament\Resources\Pengajuans\RelationManagers\LogPerubahanRelationManager;
use App\Filament\Resources\Pengajuans\RelationManagers\PencairanRelationManager;
use App\Filament\Resources\Pengajuans\RelationManagers\RabRelationManager;
use App\Filament\Resources\Pengajuans\RelationManagers\RiwayatStatusRelationManager;
use App\Filament\Resources\Pengajuans\RelationManagers\TautanBerkasRelationManager;
use App\Filament\Resources\Pengajuans\Schemas\PengajuanForm;
use App\Filament\Resources\Pengajuans\Schemas\PengajuanInfolist;
use App\Filament\Resources\Pengajuans\Tables\PengajuansTable;
use App\Models\Pengajuan;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PengajuanResource extends Resource
{
    protected static ?string $model = Pengajuan::class;

    protected static ?string $slug = 'pengajuan';

    protected static ?string $modelLabel = 'Pengajuan';

    protected static ?string $pluralModelLabel = 'Pengajuan Kegiatan';

    protected static ?string $navigationLabel = 'Pengajuan Kegiatan';

    protected static string|UnitEnum|null $navigationGroup = 'Kegiatan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'judul_kegiatan';

    public static function form(Schema $schema): Schema
    {
        return PengajuanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PengajuanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PengajuansTable::configure($table);
    }

    /**
     * Daftar dibatasi unit pengguna (BR-21).
     *
     * @return Builder<Pengajuan>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        /** @var Builder<Pengajuan> $query */
        $query = parent::getEloquentQuery();

        return $query->terlihatOleh($pengguna);
    }

    /**
     * Pengikatan URL tidak dibatasi agar akses lintas unit ditolak Policy (403), bukan 404.
     *
     * @return Builder<Pengajuan>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<Pengajuan> $query */
        $query = parent::getEloquentQuery();

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            TautanBerkasRelationManager::class,
            RabRelationManager::class,
            PencairanRelationManager::class,
            RiwayatStatusRelationManager::class,
            LogPerubahanRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengajuans::route('/'),
            'create' => CreatePengajuan::route('/create'),
            'view' => ViewPengajuan::route('/{record}'),
            'edit' => EditPengajuan::route('/{record}/edit'),
        ];
    }
}
