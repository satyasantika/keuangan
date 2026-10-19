<?php

namespace App\Notifications;

use App\Enums\TahapPersetujuan;
use App\Models\Pengajuan;
use App\Support\TautanAplikasi;
use App\Support\Uang;

class PengajuanMenungguTindakan extends NotifikasiKeuangan
{
    public function __construct(public Pengajuan $pengajuan, public TahapPersetujuan $tahap)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Pengajuan {$this->pengajuan->nomor} menunggu tindakan Anda";
    }

    public function isi(): string
    {
        return "Pengajuan \"{$this->pengajuan->judul_kegiatan}\" ({$this->pengajuan->unit->kode}) senilai ".Uang::format((string) $this->pengajuan->total_diajukan)." menunggu tahap {$this->tahap->getLabel()}.";
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengajuan($this->pengajuan->id);
    }

    public function mendesak(): bool
    {
        return true;
    }
}
