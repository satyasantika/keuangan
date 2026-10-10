<?php

namespace App\Filament\Resources\Pencairans\Schemas;

use App\Filament\Infolists\DaftarTautanBerkas;
use App\Support\Uang;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PencairanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Pencairan')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextEntry::make('pengajuan.nomor')->label('Nomor pengajuan'),
                        TextEntry::make('pengajuan.unit.nama')->label('Unit'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('jenisPencairan.nama')->label('Jenis'),
                        TextEntry::make('tanggal')->label('Tanggal')->date('d F Y'),
                        TextEntry::make('jumlah')->label('Jumlah')->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                        TextEntry::make('nomor_bukti')->label('Nomor bukti'),
                        TextEntry::make('penerima')->label('Penerima')->placeholder('—'),
                        TextEntry::make('keterangan')->label('Keterangan')->placeholder('—'),
                        TextEntry::make('alasan_batal')->label('Alasan batal')->placeholder('—')->visible(fn ($record): bool => filled($record->alasan_batal)),
                    ]),
                Section::make('Bukti (tautan)')->schema([DaftarTautanBerkas::make()]),
            ]);
    }
}
