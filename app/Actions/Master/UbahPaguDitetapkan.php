<?php

namespace App\Actions\Master;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Enums\StatusPagu;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PaguTidakCukupException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pagu;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use App\Support\VersiCacheDasbor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Perubahan jumlah pagu yang sudah ditetapkan, beralasan, oleh wadek-keuangan.
 * Jumlah baru tidak boleh lebih kecil dari komitmen pengajuan unit (BR-04).
 */
class UbahPaguDitetapkan
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly HitungSisaPagu $hitungSisaPagu,
    ) {}

    public function handle(Pagu $pagu, string $jumlahBaru, string $alasan, User $oleh): Pagu
    {
        if (! $oleh->can('pagu.setujui')) {
            throw new AuthorizationException('Hanya wakil dekan keuangan yang dapat mengubah pagu yang sudah ditetapkan.');
        }

        if (trim($alasan) === '') {
            throw new KesalahanDomain('Alasan perubahan pagu wajib diisi.');
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $jumlahBaru)) {
            throw new KesalahanDomain('Jumlah pagu harus angka desimal tidak negatif.');
        }

        $tahun = $pagu->tahunAnggaran()->value('tahun');

        return DenganKunci::jalankan("pagu:{$tahun}:{$pagu->unit_id}", fn (): Pagu => DenganKunci::jalankan("aksi:pagu:{$pagu->id}", function () use ($pagu, $jumlahBaru, $alasan, $oleh): Pagu {
            return DB::transaction(function () use ($pagu, $jumlahBaru, $alasan, $oleh): Pagu {
                $terkini = Pagu::query()->lockForUpdate()->findOrFail($pagu->getKey());

                if ($terkini->status !== StatusPagu::Ditetapkan) {
                    throw new TransisiStatusTidakSahException('Hanya pagu yang sudah ditetapkan yang diubah lewat aksi ini.');
                }

                $lama = (string) $terkini->jumlah;
                $baru = Uang::jumlahkan([$jumlahBaru]);
                $komitmen = $this->hitungSisaPagu->handle($terkini->tahun_anggaran_id, $terkini->unit_id)['komitmen'] ?? '0.00';

                if (Uang::bandingkan($baru, $komitmen) < 0) {
                    throw new PaguTidakCukupException('Pagu baru ('.Uang::format($baru).') lebih kecil dari komitmen pengajuan unit ('.Uang::format($komitmen).').');
                }

                $terkini->forceFill(['jumlah' => $baru])->save();

                $catatan = 'Perubahan pagu dari '.Uang::format($lama).' menjadi '.Uang::format($baru).': '.trim($alasan);
                $this->catatRiwayat->handle($terkini, StatusPagu::Ditetapkan, StatusPagu::Ditetapkan, $oleh, $catatan);
                VersiCacheDasbor::naikkan();

                return $terkini;
            });
        }));
    }
}
