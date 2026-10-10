<?php

namespace App\Notifications;

use App\Models\Pengajuan;
use App\Support\TautanAplikasi;

class PengingatBatasSpj extends NotifikasiKeuangan
{
    public function __construct(public Pengajuan $pengajuan, public int $hariMenujuBatas)
    {
        $this->onQueue('notifikasi');
    }

    public function judul(): string
    {
        return $this->hariMenujuBatas < 0 ? "SPJ pengajuan {$this->pengajuan->nomor} terlambat ".abs($this->hariMenujuBatas).' hari' : ($this->hariMenujuBatas === 0 ? "Batas SPJ pengajuan {$this->pengajuan->nomor} hari ini" : "Batas SPJ pengajuan {$this->pengajuan->nomor} {$this->hariMenujuBatas} hari lagi");
    }

    public function isi(): string
    {
        return "Kegiatan \"{$this->pengajuan->judul_kegiatan}\" wajib dipertanggungjawabkan paling lambat {$this->pengajuan->batas_spj?->format('d-m-Y')}. Segera lengkapi realisasi dan ajukan SPJ.";
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengajuan($this->pengajuan->id);
    }

    public function warna(): string
    {
        return $this->hariMenujuBatas < 0 ? 'danger' : 'warning';
    }

    public function mendesak(): bool
    {
        return true;
    }
}
