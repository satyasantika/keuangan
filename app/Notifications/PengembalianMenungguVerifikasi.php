<?php

namespace App\Notifications;

use App\Models\Pengembalian;
use App\Support\TautanAplikasi;
use App\Support\Uang;

class PengembalianMenungguVerifikasi extends NotifikasiKeuangan
{
    public function __construct(public Pengembalian $pengembalian)
    {
        $this->onQueue('notifikasi');
    }

    public function judul(): string
    {
        return 'Pengembalian sisa dana menunggu verifikasi';
    }

    public function isi(): string
    {
        return 'Setoran '.Uang::format((string) $this->pengembalian->jumlah)." untuk pengajuan {$this->pengembalian->pengajuan->nomor} menunggu verifikasi.";
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengembalian($this->pengembalian->id);
    }
}
