<?php

namespace App\Actions\Master;

use App\Enums\StatusTahunAnggaran;
use App\Models\TahunAnggaran;
use App\Models\User;

/**
 * Penutupan tahun anggaran (BR-20): seluruh transaksinya menjadi baca-saja.
 */
class TutupTahunAnggaran
{
    public function __construct(private readonly UbahStatusTahunAnggaran $ubahStatus) {}

    public function handle(TahunAnggaran $tahun, User $oleh, bool $konfirmasi = false, ?string $catatan = null): TahunAnggaran
    {
        return $this->ubahStatus->handle($tahun, StatusTahunAnggaran::Ditutup, $oleh, $catatan ?? 'Tahun anggaran ditutup', $konfirmasi);
    }
}
