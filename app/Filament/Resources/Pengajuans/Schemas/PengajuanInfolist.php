<?php

namespace App\Filament\Resources\Pengajuans\Schemas;

use App\Filament\Infolists\DaftarTautanBerkas;
use App\Support\Uang;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PengajuanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $rupiah = fn ($state): string => Uang::format((string) $state);

        return $schema
            ->columns(1)
            ->components([
                Section::make('Kegiatan')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextEntry::make('nomor')->label('Nomor')->placeholder('Belum diajukan'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('unit.nama')->label('Unit'),
                        TextEntry::make('judul_kegiatan')->label('Judul kegiatan')->columnSpanFull(),
                        TextEntry::make('deskripsi')->label('Deskripsi')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('tanggal_mulai_kegiatan')->label('Mulai')->date('d F Y'),
                        TextEntry::make('tanggal_selesai_kegiatan')->label('Selesai')->date('d F Y'),
                        TextEntry::make('tempat')->label('Tempat')->placeholder('—'),
                        TextEntry::make('penanggung_jawab')->label('Penanggung jawab'),
                        TextEntry::make('tahunAnggaran.nama')->label('Tahun anggaran'),
                        TextEntry::make('kategoriPenggunaanDana.nama')->label('Kategori penggunaan dana')->placeholder('—'),
                        TextEntry::make('total_diajukan')->label('Total diajukan')->formatStateUsing($rupiah),
                        TextEntry::make('total_disetujui')->label('Total disetujui')->formatStateUsing($rupiah),
                        TextEntry::make('batas_spj')->label('Batas SPJ')->date('d F Y')->placeholder('—'),
                    ]),
                Section::make('Berkas (tautan)')
                    ->collapsible()
                    ->schema([DaftarTautanBerkas::make()]),
                Section::make('RAB aktif')
                    ->description(fn ($record): string => 'Versi '.($record->rabAktif->nomor_revisi ?? 0).' · Total '.Uang::format((string) ($record->rabAktif->total ?? '0')))
                    ->schema([
                        RepeatableEntry::make('rabAktif.item')
                            ->hiddenLabel()
                            ->columns(['default' => 2, 'md' => 6])
                            ->schema([
                                TextEntry::make('akun.kode')->label('Akun'),
                                TextEntry::make('uraian')->label('Uraian')->columnSpan(['default' => 1, 'md' => 2]),
                                TextEntry::make('volume')->label('Volume')->formatStateUsing(fn ($state, $record): string => $state.' '.$record->satuan),
                                TextEntry::make('harga_satuan')->label('Harga satuan')->formatStateUsing($rupiah),
                                TextEntry::make('jumlah')->label('Jumlah')->formatStateUsing($rupiah)->weight('bold'),
                            ]),
                    ]),
            ]);
    }
}
