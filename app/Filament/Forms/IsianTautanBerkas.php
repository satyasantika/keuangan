<?php

namespace App\Filament\Forms;

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Enums\JenisTautan;
use App\Rules\TautanBerkasValid;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Isian tautan berkas (pengganti unggahan, STANDAR-TEKNIS §1a).
 */
class IsianTautanBerkas
{
    public const PETUNJUK = 'Di Google Drive: Bagikan → Akses umum: Terbatas atau Universitas Siliwangi → Salin link. Simpan di Shared Drive unit, bukan Drive pribadi.';

    /**
     * @param  list<JenisTautan>  $jenisDiizinkan
     */
    public static function make(string $nama, array $jenisDiizinkan, int $minimal = 0): Repeater
    {
        return Repeater::make($nama)
            ->label('Tautan berkas')
            ->addActionLabel('Tambah tautan')
            ->minItems($minimal)
            ->defaultItems($minimal)
            ->columns(['default' => 1, 'md' => 2])
            ->schema(self::skema($jenisDiizinkan));
    }

    /**
     * @param  list<JenisTautan>  $jenisDiizinkan
     * @return array<int, Component>
     */
    public static function skema(array $jenisDiizinkan): array
    {
        return [
            Select::make('jenis')
                ->label('Jenis')
                ->options(collect($jenisDiizinkan)->mapWithKeys(fn (JenisTautan $j) => [$j->value => $j->getLabel()])->all())
                ->default(count($jenisDiizinkan) === 1 ? $jenisDiizinkan[0]->value : null)
                ->required()
                ->live(),
            TextInput::make('label')
                ->label('Label')
                ->required()
                ->maxLength(150)
                ->placeholder('Mis. TOR Lokakarya Kurikulum'),
            TextInput::make('url')
                ->label('Tautan (URL)')
                ->url()
                ->required()
                ->maxLength(2048)
                ->rule(new TautanBerkasValid)
                ->helperText(self::PETUNJUK)
                ->columnSpanFull(),
            Checkbox::make('akses_terbatas_dikonfirmasi')
                ->label('Berkas sudah dibagikan terbatas ke domain unsil.ac.id (bukan "siapa saja yang memiliki link")')
                ->visible(fn (Get $get): bool => self::buktiKeuangan($get('jenis')))
                ->accepted(fn (Get $get): bool => self::buktiKeuangan($get('jenis')))
                ->validationMessages(['accepted' => SimpanTautanBerkas::PESAN_AKSES_TERBATAS])
                ->columnSpanFull(),
        ];
    }

    private static function buktiKeuangan(mixed $jenis): bool
    {
        $jenis = $jenis instanceof JenisTautan ? $jenis : JenisTautan::tryFrom((string) $jenis);

        return $jenis?->buktiKeuangan() ?? false;
    }
}
