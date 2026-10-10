<?php

namespace App\Notifications;

use App\Models\Pengajuan;
use App\Support\TautanAplikasi;

class PengajuanDitolak extends NotifikasiKeuangan
{
    public function __construct(public Pengajuan $pengajuan, public ?string $catatan = null)
    {
        $this->onQueue('notifikasi');
    }

    public function judul(): string
    {
        return "Pengajuan {$this->pengajuan->nomor} ditolak";
    }

    public function isi(): string
    {
        return "Pengajuan \"{$this->pengajuan->judul_kegiatan}\" ditolak. Catatan: ".($this->catatan ?? '-');
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengajuan($this->pengajuan->id);
    }

    public function warna(): string
    {
        return 'danger';
    }
}
