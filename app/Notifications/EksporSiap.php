<?php

namespace App\Notifications;

use App\Models\LaporanEkspor;
use Illuminate\Support\Facades\URL;

class EksporSiap extends NotifikasiKeuangan
{
    public function __construct(public LaporanEkspor $ekspor)
    {
        $this->onQueue('default');
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
        return "Ekspor {$this->ekspor->jenis} siap diunduh";
    }

    public function isi(): string
    {
        return 'Berkas tersedia sampai '.($this->ekspor->kedaluwarsa_pada?->format('d-m-Y H:i') ?? '-').' lalu dihapus otomatis.';
    }

    public function url(): ?string
    {
        return URL::temporarySignedRoute('ekspor.unduh', $this->ekspor->kedaluwarsa_pada ?? now()->addDay(), ['laporanEkspor' => $this->ekspor->id]);
    }

    public function warna(): string
    {
        return 'success';
    }
}
