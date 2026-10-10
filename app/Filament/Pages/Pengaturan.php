<?php

namespace App\Filament\Pages;

use App\Enums\Peran;
use App\Models\User;
use App\Support\DaftarPerluVerifikasi;
use App\Support\Pengaturan as PengaturanAplikasi;
use BackedEnum;
use Database\Seeders\PengaturanSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Halaman pengaturan kebijakan (F3.4). Ubah: admin-keuangan & super-admin; lainnya lihat.
 *
 * @property-read Schema $form
 */
class Pengaturan extends Page
{
    protected static ?string $slug = 'pengaturan';

    protected static ?string $title = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('pengaturan.lihat') ?? false;
    }

    public static function bolehUbah(): bool
    {
        return auth()->user()?->can('pengaturan.ubah') ?? false;
    }

    public function mount(): void
    {
        $nilai = [];

        foreach (array_keys(PengaturanSeeder::bawaan()) as $kunci) {
            $nilai[$kunci] = PengaturanAplikasi::get($kunci);
        }

        $this->form->fill($nilai);
    }

    public function form(Schema $schema): Schema
    {
        $formatNomor = fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
            if (! str_contains((string) $value, '{urut}')) {
                $fail('Format nomor wajib memuat {urut}.');
            }
        };

        return $schema
            ->statePath('data')
            ->disabled(! self::bolehUbah())
            ->components([
                Section::make('Persetujuan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('wajib_verifikasi_kepala_unit')->label('Wajib verifikasi kepala unit'),
                        Toggle::make('wajib_persetujuan_wadek')->label('Wajib persetujuan wadek keuangan'),
                        TextInput::make('jabatan_penyetuju_akhir')->label('Jabatan penyetuju akhir')->required()->maxLength(150)->columnSpanFull(),
                    ]),
                Section::make('SPJ')
                    ->columns(3)
                    ->schema([
                        TextInput::make('batas_hari_spj')->label('Batas hari SPJ')->required()->integer()->minValue(1)->maxValue(90)
                            ->helperText('Hari kalender; nilai kebijakan perlu verifikasi.'),
                        TextInput::make('hari_pengingat_spj')->label('Hari pengingat SPJ')->required()->regex('/^\d+(,\d+)*$/')
                            ->helperText('Mis. 3,0 = H-3 dan H-0.'),
                        TextInput::make('interval_pengingat_terlambat')->label('Interval pengingat terlambat (hari)')->required()->integer()->minValue(1)->maxValue(30),
                    ]),
                Section::make('Penomoran')
                    ->columns(2)
                    ->schema([
                        TextInput::make('format_nomor_pengajuan')->label('Format nomor pengajuan')->required()->maxLength(60)->rules([$formatNomor])
                            ->helperText('Variabel: {tahun}, {urut}.'),
                        TextInput::make('format_nomor_spj')->label('Format nomor SPJ')->required()->maxLength(60)->rules([$formatNomor]),
                    ]),
                Section::make('Lain-lain')
                    ->columns(2)
                    ->schema([
                        Select::make('pola_pengelolaan')->label('Pola pengelolaan')->options(['satker' => 'Satker', 'blu' => 'BLU'])->required()
                            ->helperText('Hanya konfigurasi (BR-25); status Unsil perlu verifikasi.'),
                        Toggle::make('honor_mengurangi_pagu')->label('Rekap honor mengurangi pagu'),
                        TextInput::make('masa_berlaku_ekspor_jam')->label('Masa berlaku ekspor (jam)')->required()->integer()->minValue(1)->maxValue(24),
                        TextInput::make('tanggal_pengingat_kunci')->label('Tanggal pengingat kunci periode')->required()->integer()->minValue(1)->maxValue(28),
                        TextInput::make('nama_fakultas')->label('Nama fakultas')->required()->maxLength(150)->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->bagianPerluVerifikasi(), $this->getFormContentComponent()]);
    }

    /**
     * Lencana butir "perlu verifikasi" yang masih terbuka — hanya super-admin & admin-keuangan.
     */
    protected function bagianPerluVerifikasi(): Component
    {
        $butir = DaftarPerluVerifikasi::terbuka();

        return Section::make('Perlu verifikasi')
            ->description('Nilai berikut masih usulan/contoh dan wajib dikonfirmasi bagian keuangan sebelum dipakai resmi.')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->iconColor('warning')
            ->visible(fn (): bool => auth()->user()?->hasAnyRole([Peran::SuperAdmin->value, Peran::AdminKeuangan->value]) ?? false)
            ->schema($butir === []
                ? [Text::make('Semua butir sudah terverifikasi.')->badge()->color('success')]
                : array_map(fn (array $b): Component => Flex::make([
                    Text::make("{$b['judul']}: {$b['jumlah']}")->badge()->color('warning')->grow(false),
                    Text::make($b['keterangan']),
                ]), $butir));
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Simpan')
                        ->submit('save')
                        ->visible(self::bolehUbah()),
                ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(self::bolehUbah(), 403);

        $data = $this->form->getState();

        /** @var User $pengguna */
        $pengguna = auth()->user();

        foreach (array_keys(PengaturanSeeder::bawaan()) as $kunci) {
            if (array_key_exists($kunci, $data)) {
                PengaturanAplikasi::set($kunci, $data[$kunci], $pengguna);
            }
        }

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
