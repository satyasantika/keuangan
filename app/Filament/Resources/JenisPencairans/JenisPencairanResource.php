<?php

namespace App\Filament\Resources\JenisPencairans;

use App\Filament\Resources\JenisPencairans\Pages\ManageJenisPencairans;
use App\Models\JenisPencairan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
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

class JenisPencairanResource extends Resource
{
    protected static ?string $model = JenisPencairan::class;

    protected static ?string $slug = 'jenis-pencairan';

    protected static ?string $modelLabel = 'Jenis Pencairan';

    protected static ?string $pluralModelLabel = 'Jenis Pencairan';

    protected static ?string $navigationLabel = 'Jenis Pencairan';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')->label('Kode')->required()->maxLength(10)->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama')->required()->maxLength(100),
                Select::make('pola')->label('Pola pengelolaan')->options(JenisPencairan::POLA)->default('semua')->required()
                    ->helperText('Hanya memengaruhi pilihan yang tampil (BR-25).'),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                Toggle::make('perlu_verifikasi')->label('Istilah perlu verifikasi')->default(true),
                Toggle::make('aktif')->label('Aktif')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('pola')->label('Pola')->formatStateUsing(fn (string $state): string => JenisPencairan::POLA[$state] ?? $state),
                TextColumn::make('perlu_verifikasi')
                    ->label('Verifikasi')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Perlu verifikasi' : 'Terkonfirmasi')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([TrashedFilter::make()])
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
            'index' => ManageJenisPencairans::route('/'),
        ];
    }
}
