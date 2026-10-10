<?php

namespace App\Filament\Resources\JenisPajaks;

use App\Filament\Resources\JenisPajaks\Pages\CreateJenisPajak;
use App\Filament\Resources\JenisPajaks\Pages\EditJenisPajak;
use App\Filament\Resources\JenisPajaks\Pages\ListJenisPajaks;
use App\Filament\Resources\JenisPajaks\RelationManagers\TarifPajakRelationManager;
use App\Models\JenisPajak;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class JenisPajakResource extends Resource
{
    protected static ?string $model = JenisPajak::class;

    protected static ?string $slug = 'jenis-pajak';

    protected static ?string $modelLabel = 'Jenis Pajak';

    protected static ?string $pluralModelLabel = 'Jenis & Tarif Pajak';

    protected static ?string $navigationLabel = 'Jenis & Tarif Pajak';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')->label('Kode')->required()->maxLength(20)->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama')->required()->maxLength(100),
                Toggle::make('aktif')->label('Aktif')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->description('Tarif hanya referensi; sistem tidak menghitung pajak (BR-16).')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('tarif_count')->label('Jumlah tarif')->counts('tarif'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TarifPajakRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJenisPajaks::route('/'),
            'create' => CreateJenisPajak::route('/create'),
            'edit' => EditJenisPajak::route('/{record}/edit'),
        ];
    }
}
