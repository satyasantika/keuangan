<?php

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Kanal WhatsApp lewat gateway HTTP. Kegagalan dilempar agar job notifikasi di-retry (tries 3).
 */
class WhatsAppChannel
{
    public static function aktifUntuk(object $notifiable): bool
    {
        return (bool) config('services.whatsapp.aktif')
            && filled(config('services.whatsapp.url'))
            && $notifiable instanceof User
            && filled($notifiable->no_hp);
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! self::aktifUntuk($notifiable) || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        /** @var User $notifiable */
        $nomor = self::normalisasiNomor((string) $notifiable->no_hp);

        if ($nomor === null) {
            return;
        }

        $diizinkan = RateLimiter::attempt('whatsapp', (int) config('services.whatsapp.batas_per_menit', 20), fn () => true, 60);

        if (! $diizinkan) {
            throw new RuntimeException('Batas kirim WhatsApp per menit tercapai; notifikasi akan dicoba ulang.');
        }

        $pesan = mb_substr((string) $notification->toWhatsApp($notifiable), 0, 300);

        Http::timeout(10)
            ->retry(2, 500)
            ->withHeaders(['Authorization' => (string) config('services.whatsapp.token')])
            ->asForm()
            ->post((string) config('services.whatsapp.url'), ['target' => $nomor, 'message' => $pesan])
            ->throw();
    }

    /**
     * 0812… / +62812… / 812… → 62812…
     */
    public static function normalisasiNomor(string $nomor): ?string
    {
        $angka = preg_replace('/\D+/', '', $nomor) ?? '';

        $angka = match (true) {
            str_starts_with($angka, '62') => $angka,
            str_starts_with($angka, '0') => '62'.substr($angka, 1),
            str_starts_with($angka, '8') => '62'.$angka,
            default => $angka,
        };

        return preg_match('/^628\d{7,12}$/', $angka) ? $angka : null;
    }
}
