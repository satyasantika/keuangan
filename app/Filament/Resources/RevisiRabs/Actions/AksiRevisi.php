<?php

namespace App\Filament\Resources\RevisiRabs\Actions;

use App\Actions\Revisi\AjukanRevisiRab;
use App\Actions\Revisi\BuatRevisiRab;
use App\Actions\Revisi\KembalikanRevisiRab;
use App\Actions\Revisi\SetujuiRevisiRab;
use App\Actions\Revisi\SimpanItemRevisi;
use App\Actions\Revisi\TolakRevisiRab;
use App\Actions\Revisi\VerifikasiRevisiRab;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\Pengajuans\Schemas\PengajuanForm;
use App\Models\Akun;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\User;
use App\Queries\PerbandinganRabQuery;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tombol alur revisi RAB; visibilitas dari RabPolicy, pemeriksaan sesungguhnya di Action.
 */
class AksiRevisi
{
    public static function buat(): Action
    {
        return Action::make('buatRevisi')
            ->label('Buat revisi')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->visible(fn (Pengajuan $record): bool => auth()->user()?->can('create', [Rab::class, $record]) ?? false)
            ->schema([Textarea::make('alasan')->label('Alasan revisi')->required()->minLength(10)])
            ->action(fn (Pengajuan $record, array $data) => self::jalankan(fn (User $u) => app(BuatRevisiRab::class)->handle($record, (string) $data['alasan'], $u), 'Revisi dibuat (draf)'));
    }

    public static function editItem(): Action
    {
        return Action::make('editItemRevisi')
            ->label('Edit item revisi')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Rab $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->modalWidth('5xl')
            ->fillForm(fn (Rab $record): array => ['items' => RabItem::query()->where('rab_id', $record->id)->orderBy('urutan')->get()
                ->map(fn (RabItem $i): array => [
                    'id' => $i->id, 'akun_id' => $i->akun_id, 'uraian' => $i->uraian, 'rincian_volume' => $i->rincian_volume,
                    'volume' => (string) $i->volume, 'satuan' => $i->satuan, 'harga_satuan' => (string) $i->harga_satuan, 'keterangan' => $i->keterangan,
                ])->all()])
            ->schema([
                Repeater::make('items')
                    ->label('Item revisi')
                    ->minItems(1)
                    ->columns(['default' => 1, 'md' => 6])
                    ->schema([
                        Hidden::make('id'),
                        Select::make('akun_id')->label('Akun')->options(fn (): array => Akun::aktifTercache()->mapWithKeys(fn (Akun $a) => [$a->id => "{$a->kode} — {$a->nama}"])->all())->searchable()->required()->columnSpan(['md' => 3]),
                        TextInput::make('uraian')->label('Uraian')->required()->maxLength(255)->columnSpan(['md' => 3]),
                        TextInput::make('rincian_volume')->label('Rincian volume')->maxLength(100)->columnSpan(['md' => 2]),
                        TextInput::make('volume')->label('Volume')->required()->regex(PengajuanForm::POLA_DESIMAL),
                        TextInput::make('satuan')->label('Satuan')->required()->maxLength(30)->datalist(PengajuanForm::SATUAN_UMUM),
                        TextInput::make('harga_satuan')->label('Harga satuan (Rp)')->required()->regex(PengajuanForm::POLA_DESIMAL)->columnSpan(['md' => 2]),
                        TextInput::make('keterangan')->label('Keterangan')->maxLength(255)->columnSpanFull(),
                    ]),
            ])
            ->action(fn (Rab $record, array $data) => self::jalankan(fn (User $u) => app(SimpanItemRevisi::class)->handle($record, array_values((array) $data['items']), $u), 'Item revisi disimpan'));
    }

    public static function bandingkan(): Action
    {
        return Action::make('bandingkan')
            ->label('Bandingkan')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (Rab $record): bool => $record->nomor_revisi > 0)
            ->modalHeading(fn (Rab $record): string => "Perbandingan revisi ke-{$record->nomor_revisi}")
            ->modalWidth('5xl')
            ->modalSubmitAction(false)
            ->modalContent(fn (Rab $record) => view('filament.perbandingan-rab', ['perbandingan' => PerbandinganRabQuery::bandingkan($record)]));
    }

    public static function ajukan(): Action
    {
        return Action::make('ajukanRevisi')
            ->label('Ajukan revisi')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Rab $record): bool => auth()->user()?->can('ajukan', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription(fn (Rab $record): string => implode(' ', AjukanRevisiRab::peringatan($record)) ?: 'Revisi akan diverifikasi keuangan.')
            ->action(fn (Rab $record) => self::jalankan(fn (User $u) => app(AjukanRevisiRab::class)->handle($record, $u), 'Revisi diajukan'));
    }

    /**
     * @return list<Action>
     */
    public static function keputusan(): array
    {
        return [
            Action::make('verifikasiRevisi')->label('Verifikasi')->icon(Heroicon::OutlinedCheck)->color('success')
                ->visible(fn (Rab $record): bool => auth()->user()?->can('verifikasi', $record) ?? false)
                ->requiresConfirmation()
                ->action(fn (Rab $record) => self::jalankan(fn (User $u) => app(VerifikasiRevisiRab::class)->handle($record, $u), 'Revisi diverifikasi')),
            Action::make('setujuiRevisi')->label('Setujui')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                ->visible(fn (Rab $record): bool => auth()->user()?->can('setujui', $record) ?? false)
                ->requiresConfirmation()
                ->action(fn (Rab $record) => self::jalankan(fn (User $u) => app(SetujuiRevisiRab::class)->handle($record, $u), 'Revisi disetujui dan berlaku')),
            Action::make('kembalikanRevisi')->label('Kembalikan')->icon(Heroicon::OutlinedArrowUturnLeft)->color('warning')
                ->visible(fn (Rab $record): bool => auth()->user()?->can('putuskan', $record) ?? false)
                ->schema([Textarea::make('catatan')->label('Catatan (wajib)')->required()->minLength(10)])
                ->action(fn (Rab $record, array $data) => self::jalankan(fn (User $u) => app(KembalikanRevisiRab::class)->handle($record, $u, (string) $data['catatan']), 'Revisi dikembalikan')),
            Action::make('tolakRevisi')->label('Tolak')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                ->visible(fn (Rab $record): bool => auth()->user()?->can('putuskan', $record) ?? false)
                ->schema([Textarea::make('catatan')->label('Catatan (wajib)')->required()->minLength(10)])
                ->action(fn (Rab $record, array $data) => self::jalankan(fn (User $u) => app(TolakRevisiRab::class)->handle($record, $u, (string) $data['catatan']), 'Revisi ditolak')),
            Action::make('cetakRevisi')->label('Cetak PDF')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->visible(fn (Rab $record): bool => $record->nomor_revisi > 0 && (auth()->user()?->can('view', $record) ?? false))
                ->url(fn (Rab $record): string => route('revisi.pdf', $record))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @param  callable(User): mixed  $aksi
     */
    private static function jalankan(callable $aksi, string $sukses): void
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        try {
            $aksi($pengguna);
        } catch (KesalahanDomain|AuthorizationException $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($sukses)->success()->send();
    }
}
