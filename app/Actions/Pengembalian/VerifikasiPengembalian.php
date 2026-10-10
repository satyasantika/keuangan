<?php

namespace App\Actions\Pengembalian;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Actions\Spj\BekukanTautanSpj;
use App\Enums\StatusPengembalian;
use App\Enums\TahapPersetujuan;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * BPP memverifikasi setoran (bukan pembuatnya), membekukan tautan bukti setor, lalu menyelesaikan pengajuan.
 */
class VerifikasiPengembalian
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly BekukanTautanSpj $bekukan,
        private readonly HitungUlangTotalPengajuan $hitungUlang,
        private readonly SelesaikanPengajuan $selesaikan,
    ) {}

    public function handle(Pengembalian $pengembalian, User $oleh, ?string $catatan = null): Pengembalian
    {
        self::pastikanVerifikator($pengembalian, $oleh);

        return DenganKunci::jalankan("aksi:pengembalian:{$pengembalian->id}", fn (): Pengembalian => DenganKunci::jalankan(
            "aksi:pengajuan-dana:{$pengembalian->pengajuan_id}",
            fn (): Pengembalian => DB::transaction(function () use ($pengembalian, $oleh, $catatan): Pengembalian {
                $pengajuan = Pengajuan::query()->lockForUpdate()->findOrFail($pengembalian->pengajuan_id);
                $terkini = Pengembalian::query()->lockForUpdate()->findOrFail($pengembalian->getKey());

                if (! $terkini->status->bolehBerpindahKe(StatusPengembalian::Diverifikasi)) {
                    throw new TransisiStatusTidakSahException('Pengembalian sudah diproses.');
                }

                $terkini->forceFill([
                    'status' => StatusPengembalian::Diverifikasi,
                    'diputuskan_oleh' => $oleh->getKey(),
                    'diputuskan_pada' => now(),
                    'catatan' => $catatan,
                ])->save();

                $riwayat = $this->catatRiwayat->handle($terkini, StatusPengembalian::Diajukan, StatusPengembalian::Diverifikasi, $oleh, $catatan, TahapPersetujuan::Bendahara);

                $spj = $pengajuan->spj()->first();

                if ($spj !== null) {
                    $this->bekukan->bekukanPemilik($spj, $terkini, $riwayat);
                }

                $this->hitungUlang->handle($pengajuan);
                $this->selesaikan->handle($pengajuan, $oleh);

                return $terkini;
            }),
        ));
    }

    public static function pastikanVerifikator(Pengembalian $pengembalian, User $oleh): void
    {
        if ($pengembalian->dibuat_oleh === $oleh->getKey()) {
            throw new PelanggaranPemisahanTugasException('Pemisahan tugas: pembuat pengembalian tidak boleh memverifikasinya.');
        }

        if (! $oleh->can('verifikasi', $pengembalian)) {
            throw new AuthorizationException('Hanya bendahara (BPP) yang dapat memverifikasi pengembalian.');
        }
    }
}
