<?php

namespace App\Filament\Resources\Pengajuans\Actions;

use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\BatalkanPengajuan;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Enums\StatusPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\Uang;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Tombol aksi status pengajuan; logika ada di App\Actions, exception domain menjadi notifikasi.
 */
class AksiPengajuan
{
    public static function ajukan(): Action
    {
        return Action::make('ajukan')
            ->label('Ajukan')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (Pengajuan $record): bool => $record->status->dapatDiubah() && (auth()->user()?->can('update', $record) ?? false))
            ->requiresConfirmation()
            ->modalHeading('Ajukan pengajuan?')
            ->modalDescription(function (Pengajuan $record): string {
                $sisa = app(HitungSisaPagu::class)->handle($record->tahun_anggaran_id, $record->unit_id, kecualiPengajuanId: $record->id);

                return 'Total RAB '.Uang::format((string) $record->total_diajukan).' · Sisa pagu unit '.Uang::format($sisa['sisa'] ?? '0')
                    .'. Setelah diajukan, data kegiatan dan RAB terkunci.';
            })
            ->action(function (Pengajuan $record): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();

                try {
                    $hasil = app(AjukanPengajuan::class)->handle($record, $pengguna);
                } catch (KesalahanDomain $e) {
                    Notification::make()->title('Gagal mengajukan')->body($e->getMessage())->danger()->send();

                    return;
                }

                $record->refresh();
                Notification::make()->title("Pengajuan {$hasil->nomor} diajukan")->success()->send();
            });
    }

    public static function cetakPdf(): Action
    {
        return Action::make('cetakPdf')
            ->label('Cetak PDF')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (Pengajuan $record): bool => $record->status !== StatusPengajuan::Draf && (auth()->user()?->can('view', $record) ?? false))
            ->url(fn (Pengajuan $record): string => route('pengajuan.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function kartuKegiatan(): Action
    {
        return Action::make('kartuKegiatan')
            ->label('Kartu kegiatan')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('gray')
            ->visible(fn (Pengajuan $record): bool => $record->nomor !== null && (auth()->user()?->can('view', $record) ?? false))
            ->url(fn (Pengajuan $record): string => route('pengajuan.kartu', $record))
            ->openUrlInNewTab();
    }

    public static function batalkan(): Action
    {
        return Action::make('batalkan')
            ->label('Batalkan')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Pengajuan $record): bool => $record->status->dapatDiubah() && (auth()->user()?->can('batalkan', $record) ?? false))
            ->schema([
                Textarea::make('alasan')->label('Alasan pembatalan')->required()->minLength(BatalkanPengajuan::MIN_ALASAN),
            ])
            ->action(function (Pengajuan $record, array $data): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();

                try {
                    app(BatalkanPengajuan::class)->handle($record, (string) $data['alasan'], $pengguna);
                } catch (KesalahanDomain $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $record->refresh();
                Notification::make()->title('Pengajuan dibatalkan')->success()->send();
            });
    }
}
