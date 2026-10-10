<?php

namespace App\Notifications;

use App\Models\Spj;
use App\Support\TautanAplikasi;
use App\Support\Uang;

class SpjMenungguVerifikasi extends NotifikasiKeuangan
{
    public function __construct(public Spj $spj)
    {
        $this->onQueue('notifikasi');
    }

    public function judul(): string
    {
        return "SPJ {$this->spj->nomor} menunggu verifikasi";
    }

    public function isi(): string
    {
        return "SPJ pengajuan {$this->spj->pengajuan->nomor} dengan realisasi ".Uang::format((string) $this->spj->total_realisasi).' menunggu verifikasi.';
    }

    public function url(): ?string
    {
        return TautanAplikasi::spj($this->spj->id);
    }

    public function mendesak(): bool
    {
        return true;
    }
}
