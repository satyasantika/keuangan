<?php

namespace App\Filament\Resources\Pengajuans\Schemas;

use App\Actions\Pengajuan\HitungSisaPagu;
use App\Enums\StatusTahunAnggaran;
use App\Models\Akun;
use App\Models\KategoriPenggunaanDana;
use App\Models\Pengajuan;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\Uang;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Form pengajuan + RAB. Kolom "Jumlah"/"Total" hanya tampilan; nilai tersimpan dihitung server (BR-02).
 */
class PengajuanForm
{
    public const POLA_DESIMAL = '/^\d+(\.\d{1,2})?$/';

    public const SATUAN_UMUM = ['OH', 'OK', 'OJ', 'OB', 'paket', 'rim', 'unit', 'kali', 'buah', 'lembar', 'kegiatan'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Kegiatan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('tahun_anggaran_id')
                            ->label('Tahun anggaran')
                            ->options(fn (): array => TahunAnggaran::query()
                                ->whereIn('status', [StatusTahunAnggaran::Berjalan->value, StatusTahunAnggaran::Perencanaan->value])
                                ->orderByDesc('tahun')
                                ->pluck('nama', 'id')
                                ->all())
                            ->default(fn (): ?string => TahunAnggaran::query()->where('status', StatusTahunAnggaran::Berjalan->value)->orderByDesc('tahun')->value('id'))
                            ->required()
                            ->live(),
                        Select::make('kategori_penggunaan_dana_id')
                            ->label('Kategori penggunaan dana')
                            ->options(fn (): array => KategoriPenggunaanDana::query()->where('aktif', true)->orderBy('urutan')->pluck('nama', 'id')->all()),
                        TextInput::make('judul_kegiatan')
                            ->label('Judul kegiatan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('deskripsi')
                            ->label('Deskripsi singkat')
                            ->helperText('Latar belakang & tujuan; TOR/KAK lengkap dilampirkan sebagai tautan.')
                            ->columnSpanFull(),
                        DatePicker::make('tanggal_mulai_kegiatan')->label('Tanggal mulai')->required(),
                        DatePicker::make('tanggal_selesai_kegiatan')->label('Tanggal selesai')->required()->afterOrEqual('tanggal_mulai_kegiatan'),
                        TextInput::make('tempat')->label('Tempat')->maxLength(255),
                        TextInput::make('penanggung_jawab')->label('Penanggung jawab')->required()->maxLength(150),
                    ]),
                Section::make('Rincian RAB')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->addActionLabel('Tambah item')
                            ->minItems(1)
                            ->required()
                            ->reorderable()
                            ->columns(['default' => 1, 'md' => 6])
                            ->itemLabel(fn (array $state): ?string => $state['uraian'] ?? null)
                            ->schema([
                                Select::make('akun_id')
                                    ->label('Akun')
                                    ->options(fn (): array => Akun::aktifTercache()->mapWithKeys(fn (Akun $a) => [$a->id => "{$a->kode} — {$a->nama}"])->all())
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(['md' => 3]),
                                TextInput::make('uraian')->label('Uraian')->required()->maxLength(255)->columnSpan(['md' => 3]),
                                TextInput::make('rincian_volume')->label('Rincian volume')->placeholder('2 org x 3 hr')->maxLength(100)->columnSpan(['md' => 2]),
                                TextInput::make('volume')
                                    ->label('Volume')
                                    ->required()
                                    ->inputMode('decimal')
                                    ->regex(self::POLA_DESIMAL)
                                    ->rules([
                                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                            if (self::sah($value) && Uang::bandingkan((string) $value, '0') <= 0) {
                                                $fail('Volume harus lebih dari 0.');
                                            }
                                        },
                                    ])
                                    ->live(onBlur: true),
                                TextInput::make('satuan')
                                    ->label('Satuan')
                                    ->required()
                                    ->maxLength(30)
                                    ->datalist(self::SATUAN_UMUM),
                                TextInput::make('harga_satuan')
                                    ->label('Harga satuan (Rp)')
                                    ->required()
                                    ->inputMode('decimal')
                                    ->regex(self::POLA_DESIMAL)
                                    ->live(onBlur: true)
                                    ->columnSpan(['md' => 2]),
                                TextInput::make('keterangan')->label('Keterangan')->maxLength(255)->columnSpan(['md' => 3]),
                                TextEntry::make('jumlah_tampil')
                                    ->label('Jumlah')
                                    ->state(fn (Get $get): string => self::jumlahTampil($get('volume'), $get('harga_satuan')))
                                    ->columnSpan(['md' => 3]),
                            ]),
                        TextEntry::make('total_tampil')
                            ->label('Total RAB')
                            ->weight('bold')
                            ->state(fn (Get $get): string => Uang::format(self::totalTampil((array) $get('items')))),
                    ]),
                Section::make('Sisa pagu unit')
                    ->schema([
                        TextEntry::make('sisa_pagu_tampil')
                            ->hiddenLabel()
                            ->state(fn (Get $get, ?Model $record): HtmlString => self::ringkasanPagu($get('tahun_anggaran_id'), (array) $get('items'), $record)),
                    ]),
            ]);
    }

    public static function jumlahTampil(mixed $volume, mixed $harga): string
    {
        if (! self::sah($volume) || ! self::sah($harga)) {
            return '—';
        }

        return Uang::format(Uang::kali((string) $volume, (string) $harga));
    }

    /**
     * @param  array<array-key, mixed>  $items
     */
    public static function totalTampil(array $items): string
    {
        $jumlah = [];

        foreach ($items as $item) {
            if (is_array($item) && self::sah($item['volume'] ?? null) && self::sah($item['harga_satuan'] ?? null)) {
                $jumlah[] = Uang::kali((string) $item['volume'], (string) $item['harga_satuan']);
            }
        }

        return Uang::jumlahkan($jumlah);
    }

    /**
     * @param  array<array-key, mixed>  $items
     */
    public static function ringkasanPagu(mixed $tahunAnggaranId, array $items, ?Model $record): HtmlString
    {
        /** @var User|null $pengguna */
        $pengguna = auth()->user();
        $unitId = $record instanceof Pengajuan ? $record->unit_id : $pengguna?->unit_id;

        if (blank($tahunAnggaranId) || $unitId === null) {
            return new HtmlString('Pilih tahun anggaran untuk melihat sisa pagu.');
        }

        $ringkas = app(HitungSisaPagu::class)->handle((string) $tahunAnggaranId, $unitId, kecualiPengajuanId: $record?->getKey());

        if ($ringkas === null) {
            return new HtmlString('—');
        }

        $total = self::totalTampil($items);
        $teks = 'Pagu '.e(Uang::format($ringkas['pagu'])).' · Komitmen '.e(Uang::format($ringkas['komitmen'])).' · <strong>Sisa '.e(Uang::format($ringkas['sisa'])).'</strong>';

        if (Uang::bandingkan($total, $ringkas['sisa']) > 0) {
            $teks .= '<br><span class="text-danger-600 dark:text-danger-400 font-semibold">Total RAB '.e(Uang::format($total)).' melebihi sisa pagu.</span>';
        }

        if (Uang::bandingkan($ringkas['pagu'], '0') === 0) {
            $teks .= '<br><span class="text-warning-600 dark:text-warning-400">Pagu unit belum ditetapkan.</span>';
        }

        return new HtmlString($teks);
    }

    private static function sah(mixed $nilai): bool
    {
        return (is_string($nilai) || is_int($nilai)) && preg_match(self::POLA_DESIMAL, (string) $nilai) === 1;
    }
}
