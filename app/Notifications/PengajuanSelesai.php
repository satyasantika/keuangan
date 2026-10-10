<?php

namespace App\Notifications;

use App\Models\Pengajuan;
use App\Support\TautanAplikasi;

class PengajuanSelesai extends NotifikasiKeuangan
{
    public function __construct(public Pengajuan $pengajuan)
    {
        $this->onQueue('notifikasi');
    }

    public function judul(): string
    {
        return "Pengajuan {$this->pengajuan->nomor} selesai";
    }

    public function isi(): string
    {
        return "Pertanggungjawaban kegiatan \"{$this->pengajuan->judul_kegiatan}\" telah selesai.";
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengajuan($this->pengajuan->id);
    }

    public function warna(): string
    {
        return 'success';
    }
}
