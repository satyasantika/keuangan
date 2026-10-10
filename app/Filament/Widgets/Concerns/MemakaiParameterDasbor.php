<?php

namespace App\Filament\Widgets\Concerns;

use App\Data\ParameterLaporan;
use App\Models\TahunAnggaran;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Parameter laporan dari filter halaman dasbor (nilai tidak sah → bawaan tahun berjalan, tahunan).
 */
trait MemakaiParameterDasbor
{
    use InteractsWithPageFilters;

    protected function parameterLaporan(): ?ParameterLaporan
    {
        $filter = $this->pageFilters ?? [];
        $tahunId = $filter['tahun_anggaran_id'] ?? null;
        $tahunId = is_string($tahunId) && TahunAnggaran::query()->whereKey($tahunId)->exists() ? $tahunId : TahunAnggaran::bawaanId();

        if ($tahunId === null) {
            return null;
        }

        $jenis = in_array($filter['jenis_periode'] ?? null, [ParameterLaporan::BULANAN, ParameterLaporan::SEMESTER, ParameterLaporan::TAHUNAN], true)
            ? $filter['jenis_periode'] : ParameterLaporan::TAHUNAN;
        $nilai = isset($filter['nilai_periode']) && is_numeric($filter['nilai_periode']) ? (int) $filter['nilai_periode'] : null;

        try {
            return new ParameterLaporan($tahunId, $jenis, $jenis === ParameterLaporan::TAHUNAN ? null : $nilai);
        } catch (\InvalidArgumentException) {
            return new ParameterLaporan($tahunId);
        }
    }
}
