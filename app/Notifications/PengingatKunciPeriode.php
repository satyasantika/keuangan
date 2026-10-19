<?php

namespace App\Notifications;

use App\Models\Periode;
use App\Support\TautanAplikasi;

class PengingatKunciPeriode extends NotifikasiKeuangan
{
    public function __construct(public Periode $periode)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Periode {$this->periode->nama} belum dikunci";
    }

    public function isi(): string
    {
        return "Periode {$this->periode->nama} masih terbuka. Kunci periode setelah laporan bulanan dikirim.";
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
