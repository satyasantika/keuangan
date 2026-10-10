<?php

namespace App\Actions\Pengajuan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengajuan;
use App\Enums\TahapPersetujuan;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan beralasan (BR-22, BR-23): admin-unit saat draf/dikembalikan; admin-keuangan saat
 * diajukan s.d. disetujui tanpa pencairan aktif. Komitmen pagu terlepas karena status dibatalkan.
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

        if (! $oleh->can('batalkan', $pengajuan)) {
            throw new AuthorizationException('Anda tidak berhak membatalkan pengajuan ini.');
        }

        return DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", function () use ($pengajuan, $alasan, $oleh): Pengajuan {
            return DB::transaction(function () use ($pengajuan, $alasan, $oleh): Pengajuan {
                $terkini = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());
                $dari = $terkini->status;

                if (! $oleh->can('batalkan', $terkini) || ! $dari->bolehBerpindahKe(StatusPengajuan::Dibatalkan)) {
                    throw new TransisiStatusTidakSahException('Pengajuan berstatus '.$dari->getLabel().' tidak dapat dibatalkan.');
                }

                if ($terkini->punyaPencairanAktif()) {
                    throw new KesalahanDomain('Pengajuan yang memiliki pencairan aktif tidak dapat dibatalkan.');
                }

                $tahap = $dari->dapatDiubah() ? null : TahapPersetujuan::Keuangan;

                $terkini->forceFill(['status' => StatusPengajuan::Dibatalkan, 'alasan_batal' => $alasan])->save();
                $this->catatRiwayat->handle($terkini, $dari, StatusPengajuan::Dibatalkan, $oleh, $alasan, $tahap);

                return $terkini;
            });
        });
    }
}
