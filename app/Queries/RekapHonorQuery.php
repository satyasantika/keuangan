<?php

namespace App\Queries;

use App\Data\ParameterLaporan;
use App\Models\User;
use App\Support\Uang;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LAP-09 & baris "Honor ujian (impor fkipapp)": rekap total honor ujian baca-saja (BR-24), hanya batch aktif.
 * Tidak digabung ke realisasi kegiatan.
 */
class RekapHonorQuery
{
    /**
     * @return Collection<int, array{bulan: int, unit: string, jenis_ujian: string, jumlah_kegiatan: int, bruto: string, pajak: string, neto: string}>
     */
    public static function untuk(ParameterLaporan $parameter, User $pengguna): Collection
    {
        return self::dasar($parameter, $pengguna)
            ->leftJoin('unit', 'unit.id', '=', 'rekap_honor_eksternal.unit_id')
            ->groupBy('periode.bulan', 'unit.kode', 'rekap_honor_eksternal.jenis_ujian')
            ->orderBy('periode.bulan')->orderBy('unit.kode')->orderBy('rekap_honor_eksternal.jenis_ujian')
            ->selectRaw('periode.bulan, unit.kode AS unit, rekap_honor_eksternal.jenis_ujian, SUM(rekap_honor_eksternal.jumlah_kegiatan) AS kegiatan, SUM(rekap_honor_eksternal.jumlah_bruto) AS bruto, SUM(rekap_honor_eksternal.jumlah_pajak) AS pajak, SUM(rekap_honor_eksternal.jumlah_neto) AS neto')
            ->get()
            ->map(fn ($b): array => [
                'bulan' => (int) $b->bulan,
                'unit' => $b->unit === null ? '(tidak terpetakan)' : (string) $b->unit,
                'jenis_ujian' => (string) $b->jenis_ujian,
                'jumlah_kegiatan' => (int) $b->kegiatan,
                'bruto' => Uang::jumlahkan([(string) $b->bruto]),
                'pajak' => Uang::jumlahkan([(string) $b->pajak]),
                'neto' => Uang::jumlahkan([(string) $b->neto]),
            ])
            ->values();
    }

    /**
     * Total bruto honor ujian pada rentang parameter (untuk baris terpisah di dasbor/LAP-01).
     */
    public static function totalBruto(ParameterLaporan $parameter, User $pengguna): string
    {
        return Uang::jumlahkan([(string) self::dasar($parameter, $pengguna)->sum('rekap_honor_eksternal.jumlah_bruto')]);
    }

    private static function dasar(ParameterLaporan $parameter, User $pengguna): Builder
    {
        [$awal, $akhir] = $parameter->rentangBulan();

        return DB::table('rekap_honor_eksternal')
            ->join('periode', 'periode.id', '=', 'rekap_honor_eksternal.periode_id')
            ->where('rekap_honor_eksternal.tahun_anggaran_id', $parameter->tahunAnggaranId)
            ->where('rekap_honor_eksternal.status', 'aktif')
            ->whereBetween('periode.bulan', [$awal, $akhir])
            ->when($parameter->unitId !== null, fn (Builder $q) => $q->where('rekap_honor_eksternal.unit_id', $parameter->unitId))
            ->when(! $pengguna->lingkupFakultas(), fn (Builder $q) => $q->where('rekap_honor_eksternal.unit_id', $pengguna->unit_id));
    }
}
