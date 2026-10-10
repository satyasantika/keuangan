<?php

namespace App\Notifications;

class ImporRekapHonorSelesai extends NotifikasiKeuangan
{
    /**
     * @param  list<string>  $galat
     */
    public function __construct(public ?string $batch, public int $jumlah, public array $galat)
    {
        $this->onQueue('notifikasi');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function judul(): string
    {
        return $this->batch === null ? 'Impor rekap honor ditolak' : "Impor rekap honor berhasil ({$this->jumlah} baris)";
    }

    public function isi(): string
    {
        return $this->batch === null ? implode(' ', array_slice($this->galat, 0, 10)) : 'Rekap honor ujian tercatat sebagai data baca-saja.';
    }

    public function url(): ?string
    {
        return null;
    }

    public function warna(): string
    {
        return $this->batch === null ? 'danger' : 'success';
    }
}
