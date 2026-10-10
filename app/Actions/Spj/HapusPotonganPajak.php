<?php

namespace App\Actions\Spj;

use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Models\PotonganPajak;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class HapusPotonganPajak
{
    public function __construct(
        private readonly CatatPotonganPajak $catatPotongan,
        private readonly HitungUlangTotalPengajuan $hitungUlang,
    ) {}

    public function handle(PotonganPajak $potongan, User $oleh): void
    {
        if (! $oleh->can('delete', $potongan)) {
            throw new AuthorizationException('Anda tidak berhak menghapus potongan pajak ini.');
        }

        $realisasi = $potongan->realisasiItem;
        $pengajuan = $realisasi->spj->pengajuan;

        DenganKunci::jalankan("aksi:pengajuan-dana:{$pengajuan->id}", fn () => DB::transaction(function () use ($potongan, $realisasi, $pengajuan): void {
            $potongan->delete();
            $this->catatPotongan->perbaruiRealisasi($realisasi);
            $this->hitungUlang->handle($pengajuan);
        }));
    }
}
