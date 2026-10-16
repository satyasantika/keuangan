<?php

namespace App\Notifications;

use App\Models\Pencairan;
use App\Support\TautanAplikasi;
use App\Support\Uang;

class PencairanDicatat extends NotifikasiKeuangan
{
    public function __construct(public Pencairan $pencairan)
    {
        $this->onQueue('default');
    }

    public function judul(): string
    {
        return "Dana pengajuan {$this->pencairan->pengajuan->nomor} dicairkan";
    }

    public function isi(): string
    {
        return 'Pencairan '.Uang::format((string) $this->pencairan->jumlah)." tanggal {$this->pencairan->tanggal->format('d-m-Y')} telah dicatat. Batas SPJ: ".($this->pencairan->pengajuan->batas_spj?->format('d-m-Y') ?? '-').'.';
    }

    public function url(): ?string
    {
        return TautanAplikasi::pengajuan($this->pencairan->pengajuan_id);
    }

    public function warna(): string
    {
        return 'success';
    }
}
