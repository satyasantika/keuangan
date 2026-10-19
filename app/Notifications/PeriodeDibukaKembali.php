<?php

namespace App\Notifications;

use App\Models\Periode;
use App\Support\TautanAplikasi;

class PeriodeDibukaKembali extends NotifikasiKeuangan
{
    public function __construct(public Periode $periode, public ?string $alasan = null)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Periode {$this->periode->nama} dibuka kembali";
    }

    public function isi(): string
    {
        return "Periode {$this->periode->nama} dibuka kembali. Alasan: ".($this->alasan ?? '-');
    }

    public function url(): ?string
    {
        return TautanAplikasi::tahunAnggaran($this->periode->tahun_anggaran_id);
    }

    public function warna(): string
    {
        return 'danger';
    }
}
