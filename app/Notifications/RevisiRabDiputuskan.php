<?php

namespace App\Notifications;

use App\Models\Rab;
use App\Support\TautanAplikasi;

class RevisiRabDiputuskan extends NotifikasiKeuangan
{
    public function __construct(public Rab $rab, public ?string $catatan = null)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Revisi RAB ke-{$this->rab->nomor_revisi} {$this->rab->status->getLabel()}";
    }

    public function isi(): string
    {
        return "Revisi RAB pengajuan {$this->rab->pengajuan->nomor} berstatus {$this->rab->status->getLabel()}. Catatan: ".($this->catatan ?? '-');
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengajuan($this->rab->pengajuan_id);
    }
}
