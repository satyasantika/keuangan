<?php

namespace App\Filament\Resources\JenisPajaks\RelationManagers;

use App\Models\JenisPajak;
use App\Models\TarifPajak;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Tarif referensi (BR-16): hanya disalin sebagai snapshot, tidak dipakai menghitung.
 */
class TarifPajakRelationManager extends RelationManager
{
    protected static string $relationship = 'tarif';

    protected static ?string $title = 'Tarif referensi';

    protected static ?string $modelLabel = 'Tarif';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uraian')->label('Uraian')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('tarif_persen')->label('Tarif (%)')->required()->numeric()->minValue(0)->maxValue(100)->step('0.01'),
                TextInput::make('dasar_hukum')->label('Dasar hukum')->maxLength(255),
                DatePicker::make('berlaku_mulai')->label('Berlaku mulai')->required(),
                DatePicker::make('berlaku_sampai')
                    ->label('Berlaku sampai')
                    ->afterOrEqual('berlaku_mulai')
                    ->rules([
                        fn (Get $get, ?Model $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                            /** @var JenisPajak $jenis */
                            $jenis = $this->getOwnerRecord();

                            if (filled($get('uraian')) && filled($get('berlaku_mulai'))
                                && TarifPajak::tumpangTindih($jenis->id, (string) $get('uraian'), (string) $get('berlaku_mulai'), $value ?: null, $record?->getKey())) {
                                $fail('Rentang berlaku tumpang tindih dengan tarif lain beruraian sama.');
                            }
                        },
                    ]),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255)->columnSpanFull(),
                Toggle::make('perlu_verifikasi')->label('Perlu verifikasi')->default(true),
                Toggle::make('aktif')->label('Aktif')->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('uraian')
            ->defaultSort('berlaku_mulai', 'desc')
            ->columns([
                TextColumn::make('uraian')->label('Uraian')->wrap(),
                TextColumn::make('tarif_persen')->label('Tarif (%)')->numeric(2),
                TextColumn::make('berlaku_mulai')->label('Mulai')->date('d M Y'),
                TextColumn::make('berlaku_sampai')->label('Sampai')->date('d M Y')->placeholder('—'),
                TextColumn::make('perlu_verifikasi')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Perlu verifikasi' : 'Terkonfirmasi')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
