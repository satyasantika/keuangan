<?php

namespace App\Queries;

use App\Enums\StatusPengajuan;
use App\Models\User;
use App\Support\Uang;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LAP-02: jumlah & nilai pengajuan per unit × status.
 */
class RekapPengajuanQuery
{
    /**
     * @return Collection<int, array{unit: string, status: string, jumlah: int, nilai_diajukan: string, nilai_disetujui: string}>
     */
    public static function untuk(User $pengguna, string $tahunAnggaranId, ?string $unitId = null): Collection
    {
        $unitId = $pengguna->lingkupFakultas() ? $unitId : $pengguna->unit_id;

        return DB::table('pengajuan')
            ->join('unit', 'unit.id', '=', 'pengajuan.unit_id')
            ->whereNull('pengajuan.deleted_at')
            ->where('pengajuan.tahun_anggaran_id', $tahunAnggaranId)
            ->when($unitId, fn (Builder $q) => $q->where('pengajuan.unit_id', $unitId))
            ->groupBy('unit.kode', 'pengajuan.status')
            ->orderBy('unit.kode')->orderBy('pengajuan.status')
            ->selectRaw('unit.kode AS unit, pengajuan.status, COUNT(*) AS jumlah, SUM(pengajuan.total_diajukan) AS diajukan, SUM(pengajuan.total_disetujui) AS disetujui')
            ->get()
            ->map(fn ($b): array => [
                'unit' => (string) $b->unit,
                'status' => StatusPengajuan::tryFrom((string) $b->status)?->getLabel() ?? (string) $b->status,
                'jumlah' => (int) $b->jumlah,
                'nilai_diajukan' => Uang::jumlahkan([(string) $b->diajukan]),
                'nilai_disetujui' => Uang::jumlahkan([(string) $b->disetujui]),
            ])
            ->values();
    }
}
