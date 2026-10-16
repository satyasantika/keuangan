<?php

namespace App\Notifications;

use App\Models\Spj;
use App\Support\TautanAplikasi;

class SpjDiputuskan extends NotifikasiKeuangan
{
    public function __construct(public Spj $spj, public ?string $catatan = null)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "SPJ {$this->spj->nomor} {$this->spj->status->getLabel()}";
    }

    public function isi(): string
    {
        return "SPJ pengajuan {$this->spj->pengajuan->nomor} berstatus {$this->spj->status->getLabel()}. Catatan: ".($this->catatan ?? '-');
    }

    public function url(): ?string
    {
        return TautanAplikasi::spj($this->spj->id);
    }
}
