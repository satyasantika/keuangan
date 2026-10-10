<?php

namespace App\Queries;

use App\Models\Periode;
use App\Models\RekapPeriode;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * LAP-07: angka snapshot periode terkunci (tidak berubah sampai dikunci ulang).
 */
class SnapshotPeriodeQuery
{
    /**
     * @return Collection<int, RekapPeriode>
     */
    public static function untuk(User $pengguna, Periode $periode): Collection
    {
        return RekapPeriode::query()
            ->with('unit')
            ->where('periode_id', $periode->id)
            ->when(! $pengguna->lingkupFakultas(), fn ($q) => $q->where('unit_id', $pengguna->unit_id))
            ->get()
            ->sortBy(fn (RekapPeriode $r) => $r->unit->kode)
            ->values();
    }
}
