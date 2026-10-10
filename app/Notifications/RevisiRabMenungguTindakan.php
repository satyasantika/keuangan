<?php

namespace App\Notifications;

use App\Models\Rab;
use App\Support\TautanAplikasi;
use App\Support\Uang;

class RevisiRabMenungguTindakan extends NotifikasiKeuangan
{
    public function __construct(public Rab $rab)
    {
        $this->onQueue('notifikasi');
    }

    public function judul(): string
    {
        return "Revisi RAB ke-{$this->rab->nomor_revisi} pengajuan {$this->rab->pengajuan->nomor} menunggu tindakan";
    }

    public function isi(): string
    {
        return 'Revisi senilai '.Uang::format((string) $this->rab->total)." menunggu {$this->rab->status->getLabel()}.";
    }

    public function url(): ?string
    {
        return TautanAplikasi::revisi();
    }
}
