<?php

namespace App\Filament\Resources\Pengajuans\Actions;

use App\Actions\Pengajuan\BatalkanPengajuan;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Actions\Persetujuan\KembalikanPengajuan;
use App\Actions\Persetujuan\KeputusanNegatif;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\TolakPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Filament\Pages\MenungguTindakan;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\Uang;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

/**
 * Tombol keputusan persetujuan. Visibilitas dari Policy; pemeriksaan sesungguhnya tetap di Action (BR-09).
 */
class AksiPersetujuan
{
    /**
     * @return list<Action>
     */
    public static function semua(): array
    {
        return [
            self::buat('verifikasi', 'Verifikasi', Heroicon::OutlinedCheck, 'success', false,
                fn (Pengajuan $p, User $u, string $c) => app(VerifikasiPengajuan::class)->handle($p, $u, $c ?: null), 'Pengajuan diverifikasi'),
            self::buat('setujui', 'Setujui', Heroicon::OutlinedCheckBadge, 'success', false,
                fn (Pengajuan $p, User $u, string $c) => app(SetujuiPengajuan::class)->handle($p, $u, $c ?: null), 'Pengajuan disetujui'),
            self::buat('kembalikan', 'Kembalikan', Heroicon::OutlinedArrowUturnLeft, 'warning', true,
                fn (Pengajuan $p, User $u, string $c) => app(KembalikanPengajuan::class)->handle($p, $u, $c), 'Pengajuan dikembalikan'),
            self::buat('tolak', 'Tolak', Heroicon::OutlinedNoSymbol, 'danger', true,
                fn (Pengajuan $p, User $u, string $c) => app(TolakPengajuan::class)->handle($p, $u, $c), 'Pengajuan ditolak'),
        ];
    }

    /**
     * Pembatalan oleh admin-keuangan (status diajukan s.d. disetujui).
     */
    public static function batalkanKeuangan(): Action
    {
        return self::buat('batalkanKeuangan', 'Batalkan', Heroicon::OutlinedXCircle, 'danger', true,
            fn (Pengajuan $p, User $u, string $c) => app(BatalkanPengajuan::class)->handle($p, $c, $u), 'Pengajuan dibatalkan', 'batalkan')
            ->visible(fn (Pengajuan $record): bool => ! $record->status->dapatDiubah() && (auth()->user()?->can('batalkan', $record) ?? false));
    }

    /**
     * @param  Closure(Pengajuan, User, string): Pengajuan  $jalankan
     */
    private static function buat(string $nama, string $label, Heroicon $ikon, string $warna, bool $catatanWajib, Closure $jalankan, string $pesanSukses, ?string $kemampuan = null): Action
    {
        $catatan = Textarea::make('catatan')->label($catatanWajib ? 'Catatan (wajib)' : 'Catatan (opsional)')->rows(3);

        if ($catatanWajib) {
            $catatan->required()->minLength(KeputusanNegatif::MIN_CATATAN);
        }

        return Action::make($nama)
            ->label($label)
            ->icon($ikon)
            ->color($warna)
            ->visible(fn (Pengajuan $record): bool => auth()->user()?->can($kemampuan ?? $nama, $record) ?? false)
            ->modalHeading(fn (Pengajuan $record): string => "{$label} pengajuan {$record->nomor}?")
            ->modalDescription(function (Pengajuan $record): string {
                $sisa = app(HitungSisaPagu::class)->handle($record->tahun_anggaran_id, $record->unit_id, kecualiPengajuanId: $record->id);

                return 'Total RAB '.Uang::format((string) $record->total_diajukan).' · Sisa pagu unit (di luar pengajuan ini) '.Uang::format($sisa['sisa'] ?? '0');
            })
            ->schema([$catatan])
            ->action(function (Pengajuan $record, array $data, Component $livewire) use ($jalankan, $pesanSukses): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();

                try {
                    $jalankan($record, $pengguna, trim((string) ($data['catatan'] ?? '')));
                } catch (KesalahanDomain|AuthorizationException $e) {
                    Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($pesanSukses)->success()->send();

                if (MenungguTindakan::canAccess()) {
                    $livewire->redirect(MenungguTindakan::getUrl(), navigate: true);
                }
            });
    }
}
