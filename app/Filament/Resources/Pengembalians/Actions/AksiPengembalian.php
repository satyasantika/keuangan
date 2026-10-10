<?php

namespace App\Filament\Resources\Pengembalians\Actions;

use App\Actions\Pengembalian\CatatPengembalian;
use App\Actions\Pengembalian\TolakPengembalian;
use App\Actions\Pengembalian\VerifikasiPengembalian;
use App\Enums\JenisTautan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPengembalian;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use App\Filament\Forms\IsianTautanBerkas;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\Spj;
use App\Models\User;
use App\Support\Uang;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class AksiPengembalian
{
    /**
     * Sisa dana yang belum disetor (sisa − pengembalian diajukan/diverifikasi).
     */
    public static function belumDisetor(Pengajuan $pengajuan): string
    {
        $tertunda = Uang::jumlahkan($pengajuan->pengembalian()
            ->whereIn('status', [StatusPengembalian::Diajukan->value, StatusPengembalian::Diverifikasi->value])
            ->pluck('jumlah')->map(fn ($j): string => (string) $j)->all());

        return Uang::kurang($pengajuan->sisaDana(), $tertunda);
    }

    private static function pengajuanDari(Model $record): Pengajuan
    {
        return $record instanceof Spj ? $record->pengajuan : ($record instanceof Pengajuan ? $record : throw new \LogicException('Pemilik tidak dikenal.'));
    }

    /**
     * "Catat pengembalian sisa dana" (admin-unit) di halaman pengajuan atau SPJ.
     */
    public static function catat(): Action
    {
        return Action::make('catatPengembalian')
            ->label('Catat pengembalian sisa dana')
            ->icon(Heroicon::OutlinedArrowUturnDown)
            ->color('gray')
            ->visible(function (Model $record): bool {
                $pengajuan = self::pengajuanDari($record);
                $statusSpj = $pengajuan->spj()->value('status');

                return $pengajuan->status === StatusPengajuan::Dicairkan
                    && in_array($statusSpj, [StatusSpj::Diajukan, StatusSpj::Diterima], true)
                    && Uang::bandingkan(self::belumDisetor($pengajuan), '0') > 0
                    && (auth()->user()?->can('create', [Pengembalian::class, $pengajuan]) ?? false);
            })
            ->schema([
                DatePicker::make('tanggal_setor')->label('Tanggal setor')->default(now())->maxDate(now())->required(),
                TextInput::make('jumlah')->label('Jumlah (Rp)')->required()->regex('/^\d+(\.\d{1,2})?$/')
                    ->default(fn (Model $record): string => self::belumDisetor(self::pengajuanDari($record))),
                TextInput::make('nomor_bukti_setor')->label('Nomor bukti setor')->required()->maxLength(100)->helperText('Mis. NTPN (istilah perlu verifikasi).'),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                IsianTautanBerkas::make('tautan_bukti', [JenisTautan::BuktiSetor], 1),
            ])
            ->action(function (Model $record, array $data): void {
                $tautan = array_values((array) ($data['tautan_bukti'] ?? []));
                unset($data['tautan_bukti']);
                self::jalankan(fn (User $u) => app(CatatPengembalian::class)->handle(self::pengajuanDari($record), $data, $tautan, $u), 'Pengembalian dicatat');
            });
    }

    public static function verifikasi(): Action
    {
        return Action::make('verifikasiPengembalian')
            ->label('Verifikasi')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Pengembalian $record): bool => auth()->user()?->can('verifikasi', $record) ?? false)
            ->requiresConfirmation()
            ->action(fn (Pengembalian $record) => self::jalankan(fn (User $u) => app(VerifikasiPengembalian::class)->handle($record, $u), 'Pengembalian diverifikasi'));
    }

    public static function tolak(): Action
    {
        return Action::make('tolakPengembalian')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Pengembalian $record): bool => auth()->user()?->can('verifikasi', $record) ?? false)
            ->schema([Textarea::make('catatan')->label('Catatan (wajib)')->required()->minLength(TolakPengembalian::MIN_CATATAN)])
            ->action(fn (Pengembalian $record, array $data) => self::jalankan(fn (User $u) => app(TolakPengembalian::class)->handle($record, $u, (string) $data['catatan']), 'Pengembalian ditolak'));
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
