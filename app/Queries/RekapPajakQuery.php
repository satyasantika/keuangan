<?php

namespace App\Queries;

use App\Models\User;
use App\Support\Uang;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LAP-05: rekap potongan pajak (informasi) per bulan × jenis pajak × unit.
 */
class RekapPajakQuery
{
    /**
     * @return Collection<int, array{bulan: int, jenis: string, unit: string, nilai: string, jumlah_bukti: int, dengan_bukti_setor: int, tanpa_bukti_setor: int}>
     */
    public static function untuk(User $pengguna, string $tahunAnggaranId): Collection
    {
        return DB::table('potongan_pajak')
            ->join('realisasi_item', 'realisasi_item.id', '=', 'potongan_pajak.realisasi_item_id')
            ->join('periode', 'periode.id', '=', 'realisasi_item.periode_id')
            ->join('spj', 'spj.id', '=', 'realisasi_item.spj_id')
            ->join('pengajuan', 'pengajuan.id', '=', 'spj.pengajuan_id')
            ->join('unit', 'unit.id', '=', 'pengajuan.unit_id')
            ->join('jenis_pajak', 'jenis_pajak.id', '=', 'potongan_pajak.jenis_pajak_id')
            ->whereNull('realisasi_item.deleted_at')
            ->where('periode.tahun_anggaran_id', $tahunAnggaranId)
            ->when(! $pengguna->lingkupFakultas(), fn (Builder $q) => $q->where('pengajuan.unit_id', $pengguna->unit_id))
            ->groupBy('periode.bulan', 'jenis_pajak.nama', 'unit.kode')
            ->orderBy('periode.bulan')->orderBy('jenis_pajak.nama')->orderBy('unit.kode')
            ->selectRaw('periode.bulan, jenis_pajak.nama AS jenis, unit.kode AS unit, SUM(potongan_pajak.nilai) AS nilai, COUNT(*) AS jumlah_bukti, SUM(CASE WHEN potongan_pajak.nomor_bukti_setor IS NULL OR potongan_pajak.nomor_bukti_setor = \'\' THEN 0 ELSE 1 END) AS dengan_setor')
            ->get()
            ->map(fn ($b): array => [
                'bulan' => (int) $b->bulan,
                'jenis' => (string) $b->jenis,
                'unit' => (string) $b->unit,
                'nilai' => Uang::jumlahkan([(string) $b->nilai]),
                'jumlah_bukti' => (int) $b->jumlah_bukti,
                'dengan_bukti_setor' => (int) $b->dengan_setor,
                'tanpa_bukti_setor' => (int) $b->jumlah_bukti - (int) $b->dengan_setor,
            ])
            ->values();
    }
}
