<?php

namespace App\Notifications;

use App\Models\LaporanEkspor;

class EksporGagal extends NotifikasiKeuangan
{
    public function __construct(public LaporanEkspor $ekspor)
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
        return "Ekspor {$this->ekspor->jenis} gagal";
    }

    public function isi(): string
    {
        return (string) ($this->ekspor->pesan_galat ?? 'Terjadi galat saat menyusun laporan.');
    }

    public function url(): ?string
    {
        return null;
    }

    public function warna(): string
    {
        return 'danger';
    }
}
