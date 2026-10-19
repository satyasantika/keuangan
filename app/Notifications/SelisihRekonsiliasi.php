<?php

namespace App\Notifications;

class SelisihRekonsiliasi extends NotifikasiKeuangan
{
    public function __construct(public int $jumlahSelisih)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Rekonsiliasi menemukan {$this->jumlahSelisih} selisih total";
    }

    public function isi(): string
    {
        return "Perintah keuangan:rekonsiliasi menemukan {$this->jumlahSelisih} selisih kolom total. Periksa dan jalankan dengan --perbaiki bila perlu.";
    }

    public function url(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function warna(): string
    {
        return 'danger';
    }
}
