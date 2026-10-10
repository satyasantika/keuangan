<?php

namespace App\Filament\Resources\Spjs\Schemas;

use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Support\Uang;
use Filament\Infolists\Components\RepeatableEntry;
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
                Section::make('Ringkasan dana')
                    ->columns(['default' => 2, 'md' => 4])
                    ->schema([
                        TextEntry::make('pengajuan.total_disetujui')->label('Disetujui')->formatStateUsing($rupiah),
                        TextEntry::make('pengajuan.total_dicairkan')->label('Dicairkan')->formatStateUsing($rupiah),
                        TextEntry::make('pengajuan.total_realisasi')->label('Realisasi')->formatStateUsing($rupiah),
                        TextEntry::make('sisa_dana')
                            ->label(fn (Spj $record): string => Uang::bandingkan($record->pengajuan->sisaDana(), '0') < 0 ? 'Kurang bayar' : 'Sisa dana')
                            ->state(fn (Spj $record): string => Uang::format(ltrim($record->pengajuan->sisaDana(), '-')))
                            ->color(fn (Spj $record): ?string => Uang::bandingkan($record->pengajuan->sisaDana(), '0') < 0 ? 'danger' : null)
                            ->helperText(fn (Spj $record): ?string => Uang::bandingkan($record->pengajuan->sisaDana(), '0') < 0 ? 'Catat pencairan tambahan (tunduk BR-12).' : null),
                    ]),
                Section::make('Per item RAB aktif')
                    ->schema([
                        RepeatableEntry::make('ringkasan_item')
                            ->hiddenLabel()
                            ->state(fn (Spj $record): array => self::ringkasanItem($record))
                            ->columns(['default' => 2, 'md' => 4])
                            ->schema([
                                TextEntry::make('uraian')->label('Item'),
                                TextEntry::make('jumlah')->label('Jumlah RAB'),
                                TextEntry::make('terealisasi')->label('Terealisasi'),
                                TextEntry::make('sisa')->label('Sisa'),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return list<array{uraian: string, jumlah: string, terealisasi: string, sisa: string}>
     */
    public static function ringkasanItem(Spj $spj): array
    {
        $hasil = [];

        foreach (RabItem::query()->where('rab_id', $spj->pengajuan->rab_aktif_id)->orderBy('urutan')->get() as $item) {
            $terealisasi = Uang::jumlahkan(RealisasiItem::query()->where('spj_id', $spj->id)->where('rab_item_id', $item->id)
                ->pluck('jumlah_bruto')->map(fn ($j): string => (string) $j)->all());

            $hasil[] = [
                'uraian' => $item->uraian,
                'jumlah' => Uang::format((string) $item->jumlah),
                'terealisasi' => Uang::format($terealisasi),
                'sisa' => Uang::format(Uang::kurang((string) $item->jumlah, $terealisasi)),
            ];
        }

        return $hasil;
    }
}
