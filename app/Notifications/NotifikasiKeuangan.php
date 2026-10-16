<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar notifikasi aplikasi: antrean 'default', kanal database (format Filament) + surel.
 * Isi TIDAK memuat URL tautan bukti maupun data penerima/vendor; cukup tombol ke halaman aplikasi.
 */
abstract class NotifikasiKeuangan extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    abstract public function judul(): string;

    abstract public function isi(): string;

    abstract public function url(): ?string;

    public function warna(): string
    {
        return 'info';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $kanal = ['database', 'mail'];

        if ($this->mendesak() && WhatsAppChannel::aktifUntuk($notifiable)) {
            $kanal[] = WhatsAppChannel::class;
        }

        return $kanal;
    }

    /**
     * Notifikasi mendesak juga dikirim lewat WhatsApp bila kanal aktif.
     */
    public function mendesak(): bool
    {
        return false;
    }

    /**
     * Teks pendek WhatsApp (≤ 300 karakter): tanpa URL tautan bukti dan tanpa data penerima.
     */
    public function toWhatsApp(object $notifiable): string
    {
        return mb_substr('[Keuangan FKIP] '.$this->judul().($this->url() !== null ? ' '.$this->url() : ''), 0, 300);
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['database' => 'default', 'mail' => 'default', WhatsAppChannel::class => 'default'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notifikasi = NotifikasiFilament::make()
            ->title($this->judul())
            ->body($this->isi())
            ->status($this->warna());

        if ($this->url() !== null) {
            $notifikasi->actions([Action::make('buka')->label('Buka')->url($this->url())->markAsRead()]);
        }

        return $notifikasi->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject('[Keuangan FKIP] '.$this->judul())
            ->greeting('Yth. Bapak/Ibu,')
            ->line($this->isi());

        if ($this->url() !== null) {
            $pesan->action('Buka di aplikasi', $this->url());
        }

        return $pesan->salutation('Keuangan FKIP Unsil');
    }
}
