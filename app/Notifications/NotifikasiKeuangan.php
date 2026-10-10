<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar notifikasi aplikasi: antrean 'notifikasi', kanal database (format Filament) + surel.
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
        return ['database', 'mail'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['database' => 'notifikasi', 'mail' => 'notifikasi', 'whatsapp' => 'notifikasi'];
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
