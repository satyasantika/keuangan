<?php

namespace App\Actions\Periode;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengembalian;
use App\Enums\StatusPeriode;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pencairan;
use App\Models\Pengembalian;
use App\Models\Periode;
use App\Models\RealisasiItem;
use App\Models\RekapPeriode;
use App\Models\RekapPeriodeAkun;
use App\Models\User;
use App\Queries\RekapPeriodeQuery;
use App\Support\DenganKunci;
use App\Support\VersiCacheDasbor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Mengunci periode bulanan (BR-19) beserta snapshot rekap per unit dan per akun.
 */
class KunciPeriode
{
    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Periode $periode, User $oleh, bool $abaikanPeringatan = false): Periode
    {
        if (! $oleh->can('kunci', $periode)) {
            throw new AuthorizationException('Hanya admin keuangan yang dapat mengunci periode.');
        }

        $peringatan = self::peringatan($periode);

        if ($peringatan !== [] && ! $abaikanPeringatan) {
            throw new KesalahanDomain('Pra-cek periode: '.implode(' ', $peringatan));
        }

        return DenganKunci::jalankan("periode:{$periode->id}", fn (): Periode => DB::transaction(function () use ($periode, $oleh): Periode {
            $terkini = Periode::query()->lockForUpdate()->findOrFail($periode->getKey());

            if ($terkini->status !== StatusPeriode::Terbuka) {
                throw new TransisiStatusTidakSahException("Periode {$terkini->nama} sudah terkunci.");
            }

            if ($terkini->bulan > 1) {
                $sebelumnya = Periode::query()->where('tahun_anggaran_id', $terkini->tahun_anggaran_id)->where('bulan', $terkini->bulan - 1)->first();

                if ($sebelumnya !== null && $sebelumnya->status !== StatusPeriode::Terkunci) {
                    throw new KesalahanDomain("Kunci periode {$sebelumnya->nama} terlebih dahulu.");
                }
            }

            $terkini->forceFill([
                'status' => StatusPeriode::Terkunci,
                'dikunci_oleh' => $oleh->getKey(),
                'dikunci_pada' => now(),
                'versi_kunci' => $terkini->versi_kunci + 1,
            ])->save();

            $this->simpanSnapshot($terkini, $oleh);
            $this->catatRiwayat->handle($terkini, StatusPeriode::Terbuka, StatusPeriode::Terkunci, $oleh, "Dikunci (versi {$terkini->versi_kunci})");

            VersiCacheDasbor::naikkan();

            return $terkini;
        }));
    }

    private function simpanSnapshot(Periode $periode, User $oleh): void
    {
        RekapPeriode::query()->where('periode_id', $periode->id)->get()->each(fn (RekapPeriode $r) => $r->delete());

        foreach (RekapPeriodeQuery::untuk($periode) as $baris) {
            $rekap = RekapPeriode::create([
                'periode_id' => $periode->id,
                'unit_id' => $baris['unit_id'],
                'versi_kunci' => $periode->versi_kunci,
                'pagu' => $baris['pagu'],
                'total_disetujui_kumulatif' => $baris['total_disetujui_kumulatif'],
                'total_pencairan' => $baris['total_pencairan'],
                'total_realisasi' => $baris['total_realisasi'],
                'total_pajak' => $baris['total_pajak'],
                'total_pengembalian' => $baris['total_pengembalian'],
                'dibuat_oleh' => $oleh->getKey(),
            ]);

            foreach ($baris['akun'] as $akunId => $nilai) {
                RekapPeriodeAkun::create(['rekap_periode_id' => $rekap->id, 'akun_id' => $akunId, ...$nilai]);
            }
        }
    }

    /**
     * Pra-cek: transaksi periode ini yang SPJ-nya belum diterima, dan pengembalian yang belum diputuskan.
     *
     * @return list<string>
     */
    public static function peringatan(Periode $periode): array
    {
        $belumDiterima = fn ($q) => $q->where(fn ($w) => $w->whereDoesntHave('spj')->orWhereHas('spj', fn ($s) => $s->where('status', '!=', StatusSpj::Diterima->value)));
        $peringatan = [];

        $pencairan = Pencairan::query()->where('periode_id', $periode->id)->where('status', StatusPencairan::Dicatat->value)
            ->whereHas('pengajuan', $belumDiterima)->count();

        if ($pencairan > 0) {
            $peringatan[] = "{$pencairan} pencairan pada periode ini milik pengajuan yang SPJ-nya belum diterima.";
        }

        $realisasi = RealisasiItem::query()->where('periode_id', $periode->id)
            ->whereHas('spj', fn ($s) => $s->where('status', '!=', StatusSpj::Diterima->value))->count();

        if ($realisasi > 0) {
            $peringatan[] = "{$realisasi} realisasi pada periode ini milik SPJ yang belum diterima.";
        }

        $pengembalian = Pengembalian::query()->where('periode_id', $periode->id)->where('status', StatusPengembalian::Diajukan->value)->count();

        if ($pengembalian > 0) {
            $peringatan[] = "{$pengembalian} pengembalian pada periode ini masih menunggu verifikasi.";
        }

        return $peringatan;
    }
}
