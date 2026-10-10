<?php

namespace App\Filament\Resources\Pencairans\Actions;

use App\Actions\Pencairan\BatalkanPencairan;
use App\Actions\Pencairan\CatatPencairan;
use App\Enums\JenisTautan;
use App\Enums\StatusPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Filament\Forms\IsianTautanBerkas;
use App\Models\JenisPencairan;
use App\Models\Pencairan;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\Pengaturan;
use App\Support\Uang;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;

class AksiPencairan
{
    /**
     * Opsi jenis pencairan aktif sesuai pola pengelolaan (BR-25: hanya memengaruhi pilihan).
     *
     * @return array<string, string>
     */
    public static function opsiJenis(): array
    {
        $pola = (string) Pengaturan::get('pola_pengelolaan', 'satker');

        return JenisPencairan::query()
            ->where('aktif', true)
            ->whereIn('pola', ['semua', $pola])
            ->orderBy('kode')
            ->get()
            ->mapWithKeys(fn (JenisPencairan $j) => [$j->id => "{$j->kode} — {$j->nama}".($j->perlu_verifikasi ? ' (istilah perlu verifikasi)' : '')])
            ->all();
    }

    /**
     * Tombol "Catat pencairan" di halaman pengajuan (BPP, status disetujui/dicairkan).
     */
    public static function catat(): Action
    {
        return Action::make('catatPencairan')
            ->label('Catat pencairan')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->visible(fn (Pengajuan $record): bool => in_array($record->status, [StatusPengajuan::Disetujui, StatusPengajuan::Dicairkan], true)
                && (auth()->user()?->can('create', [Pencairan::class, $record]) ?? false))
            ->schema([
                Select::make('jenis_pencairan_id')->label('Jenis pencairan')->options(fn (): array => self::opsiJenis())->required(),
                DatePicker::make('tanggal')->label('Tanggal')->default(now())->maxDate(now())->required(),
                TextInput::make('jumlah')
                    ->label('Jumlah (Rp)')
                    ->required()
                    ->regex('/^\d+(\.\d{1,2})?$/')
                    ->default(fn (Pengajuan $record): string => $record->sisaBelumDicairkan())
                    ->helperText(fn (Pengajuan $record): string => 'Sisa belum dicairkan: '.Uang::format($record->sisaBelumDicairkan())),
                TextInput::make('nomor_bukti')->label('Nomor bukti')->required()->maxLength(100)->helperText('Nomor kuitansi/SP2D/transfer (istilah perlu verifikasi).'),
                TextInput::make('penerima')->label('Penerima')->maxLength(150),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                IsianTautanBerkas::make('tautan_bukti', [JenisTautan::BuktiPencairan], 1),
            ])
            ->action(function (Pengajuan $record, array $data): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();
                $tautan = array_values((array) ($data['tautan_bukti'] ?? []));
                unset($data['tautan_bukti']);

                try {
                    app(CatatPencairan::class)->handle($record, $data, $tautan, $pengguna);
                } catch (KesalahanDomain|AuthorizationException $e) {
                    Notification::make()->title('Gagal mencatat pencairan')->body($e->getMessage())->danger()->send();

                    return;
                }

                $record->refresh();
                Notification::make()->title('Pencairan dicatat')->success()->send();
            });
    }

    public static function batalkan(): Action
    {
        return Action::make('batalkanPencairan')
            ->label('Batalkan')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Pencairan $record): bool => auth()->user()?->can('batalkan', $record) ?? false)
            ->schema([
                Textarea::make('alasan')->label('Alasan pembatalan')->required()->minLength(BatalkanPencairan::MIN_ALASAN),
            ])
            ->action(function (Pencairan $record, array $data): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();

                try {
                    app(BatalkanPencairan::class)->handle($record, (string) $data['alasan'], $pengguna);
                } catch (KesalahanDomain|AuthorizationException $e) {
                    Notification::make()->title('Gagal membatalkan')->body($e->getMessage())->danger()->send();

                    return;
                }

                $record->refresh();
                Notification::make()->title('Pencairan dibatalkan')->success()->send();
            });
    }
}
