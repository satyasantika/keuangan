<?php

namespace App\Filament\Resources\Pagus\Schemas;

use App\Enums\StatusTahunAnggaran;
use App\Models\Pagu;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class PaguForm
{
    public const POLA_UANG = '/^\d+(\.\d{1,2})?$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tahun_anggaran_id')
                    ->label('Tahun anggaran')
                    ->options(fn (): array => TahunAnggaran::query()
                        ->where('status', '!=', StatusTahunAnggaran::Ditutup->value)
                        ->orderByDesc('tahun')
                        ->pluck('nama', 'id')
                        ->all())
                    ->required()
                    ->disabledOn('edit'),
                Select::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::aktifTercache()->mapWithKeys(fn (Unit $u) => [$u->id => "{$u->kode} — {$u->nama}"])->all())
                    ->searchable()
                    ->required()
                    ->disabledOn('edit')
                    ->rules([
                        fn (Get $get, ?Model $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                            $ada = Pagu::withTrashed()
                                ->where('tahun_anggaran_id', $get('tahun_anggaran_id'))
                                ->where('unit_id', $value)
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($ada) {
                                $fail('Pagu unit ini untuk tahun anggaran tersebut sudah ada.');
                            }
                        },
                    ]),
                TextInput::make('jumlah')
                    ->label('Jumlah pagu (Rp)')
                    ->required()
                    ->inputMode('decimal')
                    ->regex(self::POLA_UANG)
                    ->validationMessages(['regex' => 'Gunakan angka tanpa pemisah ribuan, desimal dengan titik (mis. 150000000.00).']),
                TextInput::make('sumber_dana')
                    ->label('Sumber dana')
                    ->maxLength(100)
                    ->helperText('Teks bebas, mis. PNBP/BLU — istilah perlu verifikasi.'),
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
