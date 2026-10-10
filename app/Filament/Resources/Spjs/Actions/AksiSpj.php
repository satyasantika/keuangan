<?php

namespace App\Filament\Resources\Spjs\Actions;

use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\KembalikanSpj;
use App\Actions\Spj\SiapkanSpj;
use App\Actions\Spj\TerimaSpj;
use App\Enums\StatusPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\Spjs\SpjResource;
use App\Models\Pengajuan;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class AksiSpj
{
    /**
     * Di halaman pengajuan: buka/siapkan SPJ (admin-unit) atau lihat SPJ yang sudah ada.
     */
    public static function buka(): Action
    {
        return Action::make('bukaSpj')
            ->label(fn (Pengajuan $record): string => $record->spj()->exists() ? 'Lihat SPJ' : 'Susun SPJ')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('gray')
            ->visible(function (Pengajuan $record): bool {
                $pengguna = auth()->user();

                if (! $pengguna instanceof User) {
                    return false;
                }

                $spj = $record->spj()->first();

                return $spj !== null
                    ? $pengguna->can('view', $spj)
                    : $record->status === StatusPengajuan::Dicairkan && $pengguna->can('create', RealisasiItem::class) && $pengguna->unit_id === $record->unit_id;
            })
            ->action(function (Pengajuan $record, Component $livewire): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();

                try {
                    $spj = app(SiapkanSpj::class)->handle($record, $pengguna);
                } catch (KesalahanDomain|AuthorizationException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $livewire->redirect(SpjResource::getUrl('view', ['record' => $spj]), navigate: true);
            });
    }

    public static function ajukan(): Action
    {
        return Action::make('ajukanSpj')
            ->label('Ajukan SPJ')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Spj $record): bool => auth()->user()?->can('ajukan', $record) ?? false)
            ->modalHeading('Ajukan SPJ')
            ->modalDescription(function (Spj $record): string {
                $peringatan = AjukanSpj::peringatanTautan($record);

                return $peringatan === []
                    ? 'Pastikan semua bukti sudah lengkap.'
                    : "Peringatan tautan (tidak memblokir):\n• ".implode("\n• ", $peringatan);
            })
            ->schema([
                Checkbox::make('pernyataan_final')
                    ->label(AjukanSpj::PERNYATAAN_FINAL)
                    ->accepted()
                    ->validationMessages(['accepted' => 'Centang pernyataan berkas final untuk mengajukan SPJ.']),
            ])
            ->action(function (Spj $record, array $data): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();

                try {
                    $spj = app(AjukanSpj::class)->handle($record, $pengguna, (bool) ($data['pernyataan_final'] ?? false));
                } catch (KesalahanDomain|AuthorizationException $e) {
                    Notification::make()->title('Gagal mengajukan SPJ')->body($e->getMessage())->danger()->send();

                    return;
                }

                $record->refresh();
                Notification::make()->title("SPJ {$spj->nomor} diajukan")->success()->send();
            });
    }

    public static function terima(): Action
    {
        return Action::make('terimaSpj')
            ->label('Terima')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Spj $record): bool => auth()->user()?->can('verifikasi', $record) ?? false)
            ->modalDescription('Setelah diterima, realisasi dan tautan bukti dibekukan dan tidak dapat diubah.')
            ->schema([Textarea::make('catatan')->label('Catatan (opsional)')])
            ->action(fn (Spj $record, array $data) => self::jalankan($record, fn (User $u) => app(TerimaSpj::class)->handle($record, $u, filled($data['catatan'] ?? null) ? (string) $data['catatan'] : null), 'SPJ diterima'));
    }

    public static function kembalikan(): Action
    {
        return Action::make('kembalikanSpj')
            ->label('Kembalikan')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Spj $record): bool => auth()->user()?->can('verifikasi', $record) ?? false)
            ->schema([Textarea::make('catatan')->label('Catatan (wajib)')->required()->minLength(KembalikanSpj::MIN_CATATAN)])
            ->action(fn (Spj $record, array $data) => self::jalankan($record, fn (User $u) => app(KembalikanSpj::class)->handle($record, $u, (string) $data['catatan']), 'SPJ dikembalikan'));
    }

    /**
     * @param  callable(User): mixed  $aksi
     */
    private static function jalankan(Spj $record, callable $aksi, string $sukses): void
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        try {
            $aksi($pengguna);
        } catch (KesalahanDomain|AuthorizationException $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();

            return;
        }

        $record->refresh();
        Notification::make()->title($sukses)->success()->send();
    }
}
