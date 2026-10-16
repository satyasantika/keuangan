<?php

namespace App\Notifications;

use App\Models\RealisasiItem;
use App\Support\TautanAplikasi;

class IndikasiBuktiGanda extends NotifikasiKeuangan
{
    public function __construct(public RealisasiItem $realisasi)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Kemungkinan bukti ganda pada SPJ pengajuan {$this->realisasi->spj->pengajuan->nomor}";
    }

    public function isi(): string
    {
        return "Bukti realisasi \"{$this->realisasi->uraian}\" sama dengan bukti pada pengajuan lain. Mohon periksa saat verifikasi.";
    }

    public function url(): ?string
    {
        return TautanAplikasi::spj($this->realisasi->spj_id);
    }

    public function warna(): string
    {
        return 'warning';
    }
}
