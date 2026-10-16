<?php

namespace App\Notifications;

use App\Models\Pengajuan;
use App\Support\TautanAplikasi;
use App\Support\Uang;

class PengajuanDisetujui extends NotifikasiKeuangan
{
    public function __construct(public Pengajuan $pengajuan)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Pengajuan {$this->pengajuan->nomor} disetujui";
    }

    public function isi(): string
    {
        return "Pengajuan \"{$this->pengajuan->judul_kegiatan}\" ({$this->pengajuan->unit->kode}) disetujui sebesar ".Uang::format((string) $this->pengajuan->total_disetujui).'.';
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
