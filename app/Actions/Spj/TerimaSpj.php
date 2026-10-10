<?php

namespace App\Actions\Spj;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengembalian\SelesaikanPengajuan;
use App\Enums\Peran;
use App\Enums\StatusSpj;
use App\Enums\TahapPersetujuan;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Spj;
use App\Models\User;
use App\Policies\SpjPolicy;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * diajukan → diterima oleh admin-keuangan/BPP yang bukan penyusun SPJ (BR-07, BR-17).
 */
class TerimaSpj
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly SelesaikanPengajuan $selesaikan,
        private readonly BekukanTautanSpj $bekukan,
    ) {}

    public function handle(Spj $spj, User $oleh, ?string $catatan = null): Spj
    {
        $this->pastikanVerifikator($spj, $oleh);

        return DenganKunci::jalankan("aksi:spj:{$spj->id}", fn (): Spj => DB::transaction(function () use ($spj, $oleh, $catatan): Spj {
            $terkini = Spj::query()->lockForUpdate()->findOrFail($spj->getKey());

            if ($terkini->status !== StatusSpj::Diajukan) {
                throw new TransisiStatusTidakSahException('SPJ sudah diproses pengguna lain atau belum diajukan.');
            }

            $this->pastikanVerifikator($terkini, $oleh);

            $terkini->forceFill([
                'status' => StatusSpj::Diterima,
                'diputuskan_oleh' => $oleh->getKey(),
                'diputuskan_pada' => now(),
                'catatan_verifikasi' => $catatan,
            ])->save();

            $riwayat = $this->catatRiwayat->handle($terkini, StatusSpj::Diajukan, StatusSpj::Diterima, $oleh, $catatan, self::tahap($oleh));

            // Snapshot tautan bukti dalam transaksi yang sama; setelah ini tautan realisasi terkunci.
            $this->bekukan->handle($terkini, $riwayat);

            $this->selesaikan->handle($terkini->pengajuan()->firstOrFail(), $oleh);

            return $terkini;
        }));
    }

    public static function tahap(User $oleh): TahapPersetujuan
    {
        return $oleh->hasRole(Peran::AdminKeuangan->value) ? TahapPersetujuan::Keuangan : TahapPersetujuan::Bendahara;
    }

    public static function pastikanVerifikator(Spj $spj, User $oleh): void
    {
        if (app(SpjPolicy::class)->terlibatMenyusun($oleh, $spj)) {
            throw new PelanggaranPemisahanTugasException('Pemisahan tugas: penyusun SPJ/realisasi tidak boleh memverifikasi SPJ yang sama.');
        }

        if (! $oleh->can('verifikasi', $spj)) {
            throw new AuthorizationException('Anda tidak berhak memverifikasi SPJ ini.');
        }
    }
}
