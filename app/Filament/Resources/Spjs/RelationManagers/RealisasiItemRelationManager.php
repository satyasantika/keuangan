<?php

namespace App\Filament\Resources\Spjs\RelationManagers;

use App\Actions\Spj\CatatPotonganPajak;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\HapusRealisasi;
use App\Exceptions\KesalahanDomain;
use App\Filament\Forms\IsianTautanBerkas;
use App\Filament\Infolists\DaftarTautanBerkas;
use App\Models\JenisPajak;
use App\Models\PotonganPajak;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Models\TarifPajak;
use App\Models\User;
use App\Support\Uang;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Realisasi per item RAB beserta tautan bukti dan potongan pajak (informasi).
 */
class RealisasiItemRelationManager extends RelationManager
{
    protected static string $relationship = 'realisasiItem';

    protected static ?string $title = 'Realisasi';

    protected static ?string $modelLabel = 'Realisasi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        /** @var Spj $spj */
        $spj = $this->getOwnerRecord();
        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->can('create', RealisasiItem::class) && $pengguna->unit_id === $spj->pengajuan->unit_id && $spj->status->dapatDiubah()
            ? Response::allow()
            : Response::deny();
    }

    private function spj(): Spj
    {
        /** @var Spj $spj */
        $spj = $this->getOwnerRecord();

        return $spj;
    }

    /**
     * @return array<string, string>
     */
    private function opsiItem(?RealisasiItem $kecuali = null): array
    {
        $pengajuan = $this->spj()->pengajuan;
        $opsi = [];

        foreach (RabItem::query()->where('rab_id', $pengajuan->rab_aktif_id)->orderBy('urutan')->get() as $item) {
            $terpakai = Uang::jumlahkan(RealisasiItem::query()->where('rab_item_id', $item->id)
                ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))
                ->pluck('jumlah_bruto')->map(fn ($j): string => (string) $j)->all());
            $opsi[$item->id] = "{$item->uraian} — sisa ".Uang::format(Uang::kurang((string) $item->jumlah, $terpakai));
        }

        return $opsi;
    }

    /**
     * @return array<int, Component>
     */
    private function skemaRealisasi(bool $buat): array
    {
        $komponen = [
            Select::make('rab_item_id')->label('Item RAB')->options(fn (?RealisasiItem $record): array => $this->opsiItem($record))->required()->columnSpanFull(),
            DatePicker::make('tanggal_bukti')->label('Tanggal bukti')->maxDate(now())->required()->live(),
            TextInput::make('nomor_bukti')->label('Nomor bukti')->required()->maxLength(100),
            TextInput::make('uraian')->label('Uraian')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('penerima')->label('Penerima/penyedia')->maxLength(150),
            TextInput::make('volume')->label('Volume')->required()->regex('/^\d+(\.\d{1,2})?$/'),
            TextInput::make('harga_satuan')->label('Harga satuan (Rp)')->required()->regex('/^\d+(\.\d{1,2})?$/'),
            IsianTautanBerkas::make('tautan', CatatRealisasi::JENIS_DIIZINKAN, $buat ? 1 : 0)
                ->label($buat ? 'Tautan bukti (wajib minimal satu kuitansi/nota/faktur)' : 'Tambah tautan bukti')
                ->columnSpanFull(),
        ];

        if ($buat) {
            $komponen[] = Repeater::make('potongan_pajak')
                ->label('Potongan pajak (informasi, opsional)')
                ->defaultItems(0)
                ->addActionLabel('Tambah potongan pajak')
                ->columns(['default' => 1, 'md' => 3])
                ->schema(self::skemaPajak())
                ->columnSpanFull();
        }

        return $komponen;
    }

    /**
     * @return array<int, Component>
     */
    public static function skemaPajak(): array
    {
        return [
            Select::make('jenis_pajak_id')
                ->label('Jenis pajak')
                ->options(fn (): array => JenisPajak::query()->where('aktif', true)->orderBy('kode')->pluck('nama', 'id')->all())
                ->required()
                ->live()
                ->helperText(fn (Get $get): string => self::petunjukTarif($get('jenis_pajak_id'), $get('../../tanggal_bukti') ?? $get('tanggal_bukti_realisasi'))),
            TextInput::make('dasar_pengenaan')->label('Dasar pengenaan (Rp)')->required()->regex('/^\d+(\.\d{1,2})?$/'),
            TextInput::make('nilai')->label('Nilai pajak (Rp, sesuai bukti potong)')->required()->regex('/^\d+(\.\d{1,2})?$/'),
            TextInput::make('nomor_bukti_setor')->label('Nomor bukti setor')->maxLength(100),
            DatePicker::make('tanggal_setor')->label('Tanggal setor')->maxDate(now()),
        ];
    }

    /**
     * Petunjuk tarif referensi — hanya tampilan, tidak pernah dipakai menghitung (BR-16).
     */
    public static function petunjukTarif(mixed $jenisId, mixed $tanggal): string
    {
        $jenis = filled($jenisId) ? JenisPajak::query()->find($jenisId) : null;
        $tarif = $jenis !== null && filled($tanggal) ? TarifPajak::berlakuPada($jenis, Carbon::parse((string) $tanggal)) : null;

        return $tarif === null
            ? 'Tarif referensi belum tersedia — nilai pajak diisi sesuai bukti potong.'
            : 'Tarif referensi: '.$tarif->tarif_persen.'%'.($tarif->perlu_verifikasi ? ' (perlu verifikasi)' : '').' — nilai pajak diisi sesuai bukti potong.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components($this->skemaRealisasi(true));
    }

    public function table(Table $table): Table
    {
        $rupiah = fn ($state): string => Uang::format((string) $state);
        $fakultas = fn (): bool => auth()->user() instanceof User && auth()->user()->lingkupFakultas();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['rabItem', 'tautanBerkas']))
            ->columns([
                TextColumn::make('rabItem.uraian')->label('Item RAB')->wrap(),
                TextColumn::make('tanggal_bukti')->label('Tanggal')->date('d M Y'),
                TextColumn::make('nomor_bukti')->label('No. bukti'),
                TextColumn::make('jumlah_bruto')->label('Bruto')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('total_pajak')->label('Pajak')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('jumlah_neto')->label('Neto')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('indikasi_bukti_ganda')
                    ->label('Bukti ganda')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (bool $state): ?string => $state ? 'Kemungkinan bukti ganda' : null)
                    ->visible($fakultas),
                TextColumn::make('akses_publik')
                    ->label('Privasi')
                    ->badge()
                    ->color('danger')
                    ->state(fn (RealisasiItem $record): ?string => $record->tautanBerkas->contains('peringatan_privasi', true) ? 'Akses publik' : null),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah realisasi')
                    ->using(fn (array $data): RealisasiItem => $this->simpan($data)),
            ])
            ->recordActions([
                Action::make('bukti')
                    ->label('Bukti')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->modalHeading('Tautan bukti')
                    ->modalSubmitAction(false)
                    ->schema([DaftarTautanBerkas::make()]),
                Action::make('tambahPajak')
                    ->label('Pajak')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->visible(fn (RealisasiItem $record): bool => auth()->user()?->can('create', [PotonganPajak::class, $record]) ?? false)
                    ->fillForm(fn (RealisasiItem $record): array => ['tanggal_bukti_realisasi' => $record->tanggal_bukti->toDateString()])
                    ->schema([TextInput::make('tanggal_bukti_realisasi')->hidden()->dehydrated(false), ...self::skemaPajak()])
                    ->action(function (RealisasiItem $record, array $data): void {
                        $this->jalankan(fn (User $u) => app(CatatPotonganPajak::class)->handle($record, $data, $u), 'Potongan pajak dicatat');
                    }),
                EditAction::make()
                    ->schema(fn (): array => $this->skemaRealisasi(false))
                    ->using(fn (RealisasiItem $record, array $data): RealisasiItem => $this->simpan($data, $record)),
                DeleteAction::make()
                    ->using(function (RealisasiItem $record): bool {
                        $this->jalankan(fn (User $u) => app(HapusRealisasi::class)->handle($record, $u), 'Realisasi dihapus');

                        return true;
                    }),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function simpan(array $data, ?RealisasiItem $item = null): RealisasiItem
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $tautan = array_values((array) ($data['tautan'] ?? []));
        $pajak = array_values((array) ($data['potongan_pajak'] ?? []));
        unset($data['tautan'], $data['potongan_pajak']);

        try {
            $realisasi = app(CatatRealisasi::class)->handle($this->spj()->pengajuan, $data, $tautan, $pengguna, $item);

            foreach ($pajak as $p) {
                app(CatatPotonganPajak::class)->handle($realisasi->fresh() ?? $realisasi, $p, $pengguna);
            }

            return $realisasi;
        } catch (KesalahanDomain|AuthorizationException $e) {
            Notification::make()->title('Gagal menyimpan realisasi')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    /**
     * @param  callable(User): mixed  $aksi
     */
    private function jalankan(callable $aksi, string $sukses): void
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        try {
            $aksi($pengguna);
        } catch (KesalahanDomain|AuthorizationException $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }

        Notification::make()->title($sukses)->success()->send();
    }
}
