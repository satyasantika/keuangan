<?php

namespace App\Notifications;

use App\Models\TautanBerkas;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tautan tidak dapat diakses atau bukti keuangan tampak dibagikan publik. URL tidak disertakan.
 */
class TautanBerkasBermasalah extends Notification implements ShouldQueue
{
    use Queueable;

    public const TIDAK_DAPAT_DIAKSES = 'tidak_dapat_diakses';

    public const AKSES_PUBLIK = 'akses_publik';

    public function __construct(public TautanBerkas $tautan, public string $alasan)
    {
        $this->onQueue('notifikasi');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function judul(): string
    {
        return $this->alasan === self::AKSES_PUBLIK
            ? "Bukti \"{$this->tautan->label}\" tampak dapat dibuka publik"
            : "Tautan \"{$this->tautan->label}\" tidak dapat diakses";
    }

    public function langkah(): string
    {
        return $this->alasan === self::AKSES_PUBLIK
            ? 'Bukti keuangan memuat NPWP/rekening. Di Google Drive: Bagikan → Akses umum → ubah menjadi "Terbatas" atau "Universitas Siliwangi".'
            : 'Periksa apakah berkas masih ada dan tautannya benar, lalu perbarui tautan bila perlu.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return NotifikasiFilament::make()
            ->title($this->judul())
            ->body($this->langkah())
            ->warning()
            ->getDatabaseMessage();
    }
}
