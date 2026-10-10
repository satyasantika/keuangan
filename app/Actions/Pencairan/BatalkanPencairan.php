<?php

namespace App\Actions\Pencairan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengajuan;
use App\Enums\TahapPersetujuan;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pencairan;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi pencairan tanpa menghapus jejak (BR-22): dicatat → dibatalkan beralasan.
 */
class BatalkanPencairan
{
    public const MIN_ALASAN = 10;

    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly HitungUlangTotalPengajuan $hitungUlang,
    ) {}

    public function handle(Pencairan $pencairan, string $alasan, User $oleh): Pencairan
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            throw new KesalahanDomain('Alasan pembatalan wajib diisi minimal '.self::MIN_ALASAN.' karakter.');
        }

        if (! $oleh->can('batalkan', $pencairan)) {
            throw new AuthorizationException('Hanya bendahara (BPP) yang dapat membatalkan pencairan.');
        }

        return DenganKunci::jalankan("aksi:pengajuan-dana:{$pencairan->pengajuan_id}", fn (): Pencairan => DB::transaction(function () use ($pencairan, $alasan, $oleh): Pencairan {
            $pengajuan = Pengajuan::query()->lockForUpdate()->findOrFail($pencairan->pengajuan_id);
            $terkini = Pencairan::query()->lockForUpdate()->findOrFail($pencairan->getKey());

            if (! $terkini->status->bolehBerpindahKe(StatusPencairan::Dibatalkan)) {
                throw new TransisiStatusTidakSahException('Pencairan sudah dibatalkan.');
            }

            if ($pengajuan->spjSudahDiterima()) {
                throw new KesalahanDomain('SPJ pengajuan sudah diterima; pencairan tidak dapat dibatalkan.');
            }

            // Guard MenjagaPeriodeTerkunci menolak bila periode pencairan terkunci (BR-19).
            $terkini->forceFill([
                'status' => StatusPencairan::Dibatalkan,
                'dibatalkan_oleh' => $oleh->getKey(),
                'dibatalkan_pada' => now(),
                'alasan_batal' => $alasan,
            ])->save();

            $this->catatRiwayat->handle($terkini, StatusPencairan::Dicatat, StatusPencairan::Dibatalkan, $oleh, $alasan, TahapPersetujuan::Bendahara);

            if ($pengajuan->status === StatusPengajuan::Dicairkan && ! $pengajuan->punyaPencairanAktif() && ! $pengajuan->punyaRealisasi()) {
                $pengajuan->forceFill(['status' => StatusPengajuan::Disetujui, 'batas_spj' => null])->save();
                $this->catatRiwayat->handle($pengajuan, StatusPengajuan::Dicairkan, StatusPengajuan::Disetujui, $oleh, 'Semua pencairan dibatalkan', TahapPersetujuan::Bendahara);
            }

            $this->hitungUlang->handle($pengajuan);

            return $terkini;
        }));
    }
}
