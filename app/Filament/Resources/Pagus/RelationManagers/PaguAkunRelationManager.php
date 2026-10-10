<?php

namespace App\Filament\Resources\Pagus\RelationManagers;

use App\Enums\StatusPagu;
use App\Filament\Resources\Pagus\Schemas\PaguForm;
use App\Models\Akun;
use App\Models\Pagu;
use App\Support\Uang;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rincian pagu per akun (opsional); dapat diubah hanya selama pagu draf.
 */
class PaguAkunRelationManager extends RelationManager
{
    protected static string $relationship = 'rincianAkun';

    protected static ?string $title = 'Rincian per akun';

    protected static ?string $modelLabel = 'Rincian akun';

    public function isReadOnly(): bool
    {
        /** @var Pagu $pagu */
        $pagu = $this->getOwnerRecord();

        return $pagu->status !== StatusPagu::Draf;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('akun_id')
                    ->label('Akun')
                    ->options(fn (): array => Akun::aktifTercache()->mapWithKeys(fn (Akun $a) => [$a->id => "{$a->kode} — {$a->nama}"])->all())
                    ->searchable()
                    ->required()
                    ->unique(table: 'pagu_akun', column: 'akun_id', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('pagu_id', $this->getOwnerRecord()->getKey())),
                TextInput::make('jumlah')
                    ->label('Jumlah (Rp)')
                    ->required()
                    ->regex(PaguForm::POLA_UANG)
                    ->rules([
                        fn (?Model $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                            if (! is_string($value) || ! preg_match(PaguForm::POLA_UANG, $value)) {
                                return;
                            }

                            /** @var Pagu $pagu */
                            $pagu = $this->getOwnerRecord();
                            $total = Uang::jumlahkan([$pagu->totalRincianAkun($record?->getKey()), $value]);

                            if (Uang::bandingkan($total, (string) $pagu->jumlah) > 0) {
                                $fail('Σ rincian akun ('.Uang::format($total).') melebihi pagu ('.Uang::format((string) $pagu->jumlah).').');
                            }
                        },
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('akun'))
            ->columns([
                TextColumn::make('akun.kode')->label('Kode akun'),
                TextColumn::make('akun.nama')->label('Nama akun')->wrap(),
                TextColumn::make('jumlah')->label('Jumlah')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
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
