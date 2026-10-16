<?php

namespace App\Notifications;

use App\Models\Periode;
use App\Support\TautanAplikasi;

class PeriodeDikunci extends NotifikasiKeuangan
{
    public function __construct(public Periode $periode)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Periode {$this->periode->nama} dikunci";
    }

    public function isi(): string
    {
        return "Periode {$this->periode->nama} telah dikunci (versi {$this->periode->versi_kunci}); transaksi bertanggal periode ini tidak dapat diubah.";
    }

    public function url(): ?string
    {
        return TautanAplikasi::tahunAnggaran($this->periode->tahun_anggaran_id);
    }

    public function warna(): string
    {
        return 'warning';
    }
}
