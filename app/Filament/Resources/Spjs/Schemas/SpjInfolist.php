<?php

namespace App\Filament\Resources\Spjs\Schemas;

use App\Support\Uang;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SpjInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $rupiah = fn ($state): string => Uang::format((string) $state);

        return $schema
            ->columns(1)
            ->components([
                Section::make('SPJ')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextEntry::make('nomor')->label('Nomor SPJ')->placeholder('Belum diajukan'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('pengajuan.nomor')->label('Nomor pengajuan'),
                        TextEntry::make('pengajuan.judul_kegiatan')->label('Kegiatan')->columnSpanFull(),
                        TextEntry::make('pengajuan.unit.nama')->label('Unit'),
                        TextEntry::make('total_realisasi')->label('Total realisasi')->formatStateUsing($rupiah),
                        TextEntry::make('total_pajak')->label('Total pajak (informasi)')->formatStateUsing($rupiah),
                        TextEntry::make('pernyataan_final_pada')->label('Pernyataan berkas final')->dateTime('d F Y H:i')->placeholder('—'),
                        TextEntry::make('catatan_verifikasi')->label('Catatan verifikasi')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
