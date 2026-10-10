<?php

namespace App\Queries;

use App\Enums\StatusPengembalian;
use App\Models\User;
use App\Support\Uang;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LAP-06: rekap pengembalian sisa dana terverifikasi per bulan × unit.
 */
class RekapPengembalianQuery
{
    /**
     * @return Collection<int, array{bulan: int, unit: string, jumlah: string, banyak: int}>
     */
    public static function untuk(User $pengguna, string $tahunAnggaranId): Collection
    {
        return DB::table('pengembalian')
            ->join('periode', 'periode.id', '=', 'pengembalian.periode_id')
            ->join('pengajuan', 'pengajuan.id', '=', 'pengembalian.pengajuan_id')
            ->join('unit', 'unit.id', '=', 'pengajuan.unit_id')
            ->where('periode.tahun_anggaran_id', $tahunAnggaranId)
            ->where('pengembalian.status', StatusPengembalian::Diverifikasi->value)
            ->when(! $pengguna->lingkupFakultas(), fn (Builder $q) => $q->where('pengajuan.unit_id', $pengguna->unit_id))
            ->groupBy('periode.bulan', 'unit.kode')
            ->orderBy('periode.bulan')->orderBy('unit.kode')
            ->selectRaw('periode.bulan, unit.kode AS unit, SUM(pengembalian.jumlah) AS jumlah, COUNT(*) AS banyak')
            ->get()
            ->map(fn ($b): array => ['bulan' => (int) $b->bulan, 'unit' => (string) $b->unit, 'jumlah' => Uang::jumlahkan([(string) $b->jumlah]), 'banyak' => (int) $b->banyak])
            ->values();
    }
}
