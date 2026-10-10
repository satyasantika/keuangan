<?php

namespace App\Queries;

use App\Enums\JenisUnit;
use App\Models\KategoriPenggunaanDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\Uang;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LAP-08: penggunaan dana (Σ realisasi) per prodi × kategori × tahun TS-2, TS-1, TS (format instrumen perlu verifikasi).
 */
class PenggunaanDanaQuery
{
    /**
     * @return Collection<int, array{unit: string, kategori: string, ts2: string, ts1: string, ts: string, rata_rata: string}>
     */
    public static function untuk(User $pengguna, int $tahunTs, ?string $unitId = null): Collection
    {
        $tahun = [$tahunTs - 2, $tahunTs - 1, $tahunTs];
        $tahunId = TahunAnggaran::query()->whereIn('tahun', $tahun)->pluck('tahun', 'id');
        $unitId = $pengguna->lingkupFakultas() ? $unitId : $pengguna->unit_id;

        $baris = DB::table('realisasi_item')
            ->join('spj', 'spj.id', '=', 'realisasi_item.spj_id')
            ->join('pengajuan', 'pengajuan.id', '=', 'spj.pengajuan_id')
            ->join('unit', 'unit.id', '=', 'pengajuan.unit_id')
            ->whereNull('realisasi_item.deleted_at')
            ->where('unit.jenis', JenisUnit::Prodi->value)
            ->whereNotNull('pengajuan.kategori_penggunaan_dana_id')
            ->whereIn('pengajuan.tahun_anggaran_id', $tahunId->keys())
            ->when($unitId, fn (Builder $q) => $q->where('pengajuan.unit_id', $unitId))
            ->groupBy('unit.kode', 'pengajuan.kategori_penggunaan_dana_id', 'pengajuan.tahun_anggaran_id')
            ->selectRaw('unit.kode AS unit, pengajuan.kategori_penggunaan_dana_id AS kategori_id, pengajuan.tahun_anggaran_id AS tahun_id, SUM(realisasi_item.jumlah_bruto) AS n')
            ->get();

        $kategori = KategoriPenggunaanDana::query()->orderBy('urutan')->pluck('nama', 'id');
        $hasil = collect();

        foreach ($baris->groupBy('unit') as $unit => $perUnit) {
            foreach ($kategori as $katId => $katNama) {
                $nilai = [];

                foreach ($tahun as $i => $th) {
                    $nilai[$i] = Uang::jumlahkan($perUnit->where('kategori_id', $katId)
                        ->filter(fn ($b) => (int) ($tahunId[$b->tahun_id] ?? 0) === $th)
                        ->pluck('n')->map(fn ($n): string => (string) $n)->all());
                }

                if (Uang::bandingkan(Uang::jumlahkan($nilai), '0') === 0) {
                    continue;
                }

                $hasil->push([
                    'unit' => (string) $unit,
                    'kategori' => (string) $katNama,
                    'ts2' => $nilai[0],
                    'ts1' => $nilai[1],
                    'ts' => $nilai[2],
                    'rata_rata' => (string) BigDecimal::of(Uang::jumlahkan($nilai))->dividedBy(3, 2, RoundingMode::HalfUp),
                ]);
            }
        }

        return $hasil->values();
    }
}
