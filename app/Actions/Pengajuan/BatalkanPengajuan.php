<?php

namespace App\Actions\Pengajuan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan beralasan (BR-22, BR-23). Tahap ini: admin-unit pada status draf/dikembalikan.
 */
class BatalkanPengajuan
{
    public const MIN_ALASAN = 10;

    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Pengajuan $pengajuan, string $alasan, User $oleh): Pengajuan
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            throw new KesalahanDomain('Alasan pembatalan wajib diisi minimal '.self::MIN_ALASAN.' karakter.');
        }

        if (! $oleh->can('update', $pengajuan)) {
            throw new AuthorizationException('Anda tidak berhak membatalkan pengajuan ini.');
        }

        return DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", function () use ($pengajuan, $alasan, $oleh): Pengajuan {
            return DB::transaction(function () use ($pengajuan, $alasan, $oleh): Pengajuan {
                $terkini = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());
                $dari = $terkini->status;

                if (! $dari->dapatDiubah()) {
                    throw new TransisiStatusTidakSahException('Pengajuan berstatus '.$dari->getLabel().' tidak dapat dibatalkan oleh unit.');
                }

                $terkini->forceFill(['status' => StatusPengajuan::Dibatalkan, 'alasan_batal' => $alasan])->save();
                $this->catatRiwayat->handle($terkini, $dari, StatusPengajuan::Dibatalkan, $oleh, $alasan);

                return $terkini;
            });
        });
    }
}
