<?php

namespace App\Queries;

use App\Enums\StatusPengajuan;
use App\Enums\StatusSpj;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * LAP-04: pengajuan dicairkan dengan batas SPJ lewat atau ≤ 7 hari lagi dan SPJ belum diterima.
 */
class SpjTerlambatQuery
{
    public const HARI_JATUH_TEMPO = 7;

    /**
     * @return Builder<Pengajuan>
     */
    public static function query(User $pengguna, ?string $tahunAnggaranId = null): Builder
    {
        return Pengajuan::query()
            ->terlihatOleh($pengguna)
            ->with(['unit', 'spj'])
            ->when($tahunAnggaranId, fn (Builder $q) => $q->where('tahun_anggaran_id', $tahunAnggaranId))
            ->where('status', StatusPengajuan::Dicairkan->value)
            ->whereNotNull('batas_spj')
            ->whereDate('batas_spj', '<=', now()->addDays(self::HARI_JATUH_TEMPO)->toDateString())
            ->where(fn (Builder $q) => $q->whereDoesntHave('spj')->orWhereHas('spj', fn (Builder $s) => $s->where('status', '!=', StatusSpj::Diterima->value)))
            ->orderBy('batas_spj');
    }

    /**
     * @return Collection<int, array{unit: string, nomor: string, judul: string, batas_spj: string, hari_terlambat: int<0, max>, status_spj: string}>
     */
    public static function untuk(User $pengguna, ?string $tahunAnggaranId = null): Collection
    {
        $hariIni = now()->startOfDay();

        return self::query($pengguna, $tahunAnggaranId)->get()->map(fn (Pengajuan $p): array => [
            'unit' => $p->unit->kode,
            'nomor' => (string) $p->nomor,
            'judul' => $p->judul_kegiatan,
            'batas_spj' => $p->batas_spj?->format('d-m-Y') ?? '',
            'hari_terlambat' => max(0, (int) $p->batas_spj?->copy()->startOfDay()->diffInDays($hariIni, false)),
            'status_spj' => $p->spj?->status->getLabel() ?? 'Belum disusun',
        ])->values();
    }
}
