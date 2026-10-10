<?php

namespace App\Filament\Resources\KategoriPenggunaanDanas;

use App\Filament\Resources\KategoriPenggunaanDanas\Pages\ManageKategoriPenggunaanDanas;
use App\Models\KategoriPenggunaanDana;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class KategoriPenggunaanDanaResource extends Resource
{
    protected static ?string $model = KategoriPenggunaanDana::class;

    protected static ?string $slug = 'kategori-penggunaan-dana';

    protected static ?string $modelLabel = 'Kategori Penggunaan Dana';

    protected static ?string $pluralModelLabel = 'Kategori Penggunaan Dana';

    protected static ?string $navigationLabel = 'Kategori Penggunaan Dana';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')->label('Kode')->required()->maxLength(40)->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama')->required()->maxLength(150),
                TextInput::make('urutan')->label('Urutan')->numeric()->integer()->minValue(0)->default(0),
                Toggle::make('aktif')->label('Aktif')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->description('CONTOH — format instrumen akreditasi (LKPS/LAMDIK) perlu verifikasi.')
            ->columns([
                TextColumn::make('urutan')->label('#'),
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKategoriPenggunaanDanas::route('/'),
        ];
    }
}
