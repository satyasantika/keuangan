<?php

namespace App\Actions\Periode;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPeriode;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Periode;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\VersiCacheDasbor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Buka kunci periode oleh wadek-keuangan dengan alasan; berurutan dari periode terbaru (BR-19).
 * Snapshot lama dipertahankan sampai periode dikunci ulang.
 */
class BukaKunciPeriode
{
    public const MIN_ALASAN = 20;

    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Periode $periode, string $alasan, User $oleh): Periode
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            throw new KesalahanDomain('Alasan buka kunci wajib diisi minimal '.self::MIN_ALASAN.' karakter.');
        }

        if (! $oleh->can('bukaKunci', $periode)) {
            throw new AuthorizationException('Hanya wakil dekan keuangan yang dapat membuka kunci periode.');
        }

        return DenganKunci::jalankan("periode:{$periode->id}", fn (): Periode => DB::transaction(function () use ($periode, $alasan, $oleh): Periode {
            $terkini = Periode::query()->lockForUpdate()->findOrFail($periode->getKey());

            if ($terkini->status !== StatusPeriode::Terkunci) {
                throw new TransisiStatusTidakSahException("Periode {$terkini->nama} tidak sedang terkunci.");
            }

            $sesudah = Periode::query()
                ->where('tahun_anggaran_id', $terkini->tahun_anggaran_id)
                ->where('bulan', '>', $terkini->bulan)
                ->where('status', StatusPeriode::Terkunci->value)
                ->orderByDesc('bulan')
                ->first();

            if ($sesudah !== null) {
                throw new KesalahanDomain("Buka kunci berurutan dari periode terbaru: buka {$sesudah->nama} terlebih dahulu.");
            }

            $terkini->forceFill(['status' => StatusPeriode::Terbuka])->save();
            $this->catatRiwayat->handle($terkini, StatusPeriode::Terkunci, StatusPeriode::Terbuka, $oleh, $alasan);

            VersiCacheDasbor::naikkan();

            return $terkini;
        }));
    }
}
