<?php

namespace App\Notifications;

use App\Models\Pengajuan;
use App\Support\TautanAplikasi;

class PengajuanDikembalikan extends NotifikasiKeuangan
{
    public function __construct(public Pengajuan $pengajuan, public ?string $catatan = null)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Pengajuan {$this->pengajuan->nomor} dikembalikan";
    }

    public function isi(): string
    {
        return "Pengajuan \"{$this->pengajuan->judul_kegiatan}\" dikembalikan untuk perbaikan. Catatan: ".($this->catatan ?? '-');
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengajuan($this->pengajuan->id);
    }

    public function warna(): string
    {
        return 'warning';
    }
}
