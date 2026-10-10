<?php

namespace App\Queries;

use App\Actions\Pengajuan\HitungSisaPagu;
use App\Data\ParameterLaporan;
use App\Enums\StatusPagu;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uang;
use App\Support\VersiCacheDasbor;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu sumber angka realisasi anggaran untuk dasbor dan LAP-01. Agregasi SUM di SQL (string desimal).
 * Bila seluruh rentang berada pada periode terkunci, angka transaksi diambil dari snapshot rekap_periode.
 */
class LaporanRealisasiQuery
{
    /**
     * @return Collection<int, array{unit_id: string, unit_kode: string, unit_nama: string, pagu: string, komitmen: string, disetujui: string, dicairkan: string, realisasi: string, realisasi_kumulatif: string, sisa_pagu: string, serapan_persen: string|null, sumber: string}>
     */
    public static function untuk(ParameterLaporan $parameter, User $pengguna): Collection
    {
        $unitId = $pengguna->lingkupFakultas() ? $parameter->unitId : $pengguna->unit_id;

        if (! $pengguna->lingkupFakultas() && $unitId === null) {
            return collect();
        }

        $parameter = new ParameterLaporan($parameter->tahunAnggaranId, $parameter->jenisPeriode, $parameter->nilaiPeriode, $unitId, $parameter->akunId);

        /** @var Collection<int, array{unit_id: string, unit_kode: string, unit_nama: string, pagu: string, komitmen: string, disetujui: string, dicairkan: string, realisasi: string, realisasi_kumulatif: string, sisa_pagu: string, serapan_persen: string|null, sumber: string}> */
        return VersiCacheDasbor::ingat('lra:'.$parameter->kunciCache(), fn (): Collection => self::hitung($parameter));
    }

    /**
     * Total seluruh baris (untuk baris total laporan/dasbor).
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return array{pagu: string, komitmen: string, disetujui: string, dicairkan: string, realisasi: string, realisasi_kumulatif: string, sisa_pagu: string, serapan_persen: string|null}
     */
    public static function total(Collection $baris): array
    {
        $jumlah = fn (string $kolom): string => Uang::jumlahkan($baris->pluck($kolom)->map(fn ($n): string => (string) $n)->all());
        $pagu = $jumlah('pagu');
        $kumulatif = $jumlah('realisasi_kumulatif');

        return [
            'pagu' => $pagu,
            'komitmen' => $jumlah('komitmen'),
            'disetujui' => $jumlah('disetujui'),
            'dicairkan' => $jumlah('dicairkan'),
            'realisasi' => $jumlah('realisasi'),
            'realisasi_kumulatif' => $kumulatif,
            'sisa_pagu' => $jumlah('sisa_pagu'),
            'serapan_persen' => self::serapan($kumulatif, $pagu),
        ];
    }

    public static function serapan(string $realisasi, string $pagu): ?string
    {
        if (Uang::bandingkan($pagu, '0') <= 0) {
            return null;
        }

        return (string) BigDecimal::of($realisasi)->multipliedBy(100)->dividedBy($pagu, 2, RoundingMode::HalfUp);
    }

    /**
     * @return Collection<int, array{unit_id: string, unit_kode: string, unit_nama: string, pagu: string, komitmen: string, disetujui: string, dicairkan: string, realisasi: string, realisasi_kumulatif: string, sisa_pagu: string, serapan_persen: string|null, sumber: string}>
     */
    private static function hitung(ParameterLaporan $p): Collection
    {
        [$awal, $akhir] = $p->rentangBulan();
        $periode = Periode::query()->where('tahun_anggaran_id', $p->tahunAnggaranId)->orderBy('bulan')->get();
        $dalamRentang = $periode->whereBetween('bulan', [$awal, $akhir]);
        $sampaiAkhir = $periode->where('bulan', '<=', $akhir);
        $akhirPeriode = $periode->firstWhere('bulan', $akhir);

        $snapshot = $p->akunId === null
            && $sampaiAkhir->isNotEmpty()
            && $sampaiAkhir->every(fn (Periode $x) => $x->status === StatusPeriode::Terkunci);

        $unitDipakai = $p->unitId !== null ? [$p->unitId] : null;

        $pagu = self::paguPerUnit($p, $unitDipakai);

        if ($snapshot) {
            $rekap = DB::table('rekap_periode')->whereIn('periode_id', $sampaiAkhir->pluck('id'))
                ->when($unitDipakai, fn (Builder $q) => $q->whereIn('unit_id', $unitDipakai))->get();
            $idRentang = $dalamRentang->pluck('id')->all();
            $idAkhir = $akhirPeriode?->id;

            $dicairkan = self::petakan($rekap->whereIn('periode_id', $idRentang), 'total_pencairan');
            $realisasi = self::petakan($rekap->whereIn('periode_id', $idRentang), 'total_realisasi');
            $kumulatif = self::petakan($rekap, 'total_realisasi');
            $disetujui = self::petakan($rekap->where('periode_id', $idAkhir), 'total_disetujui_kumulatif');
            $pagu = array_replace($pagu, self::petakan($rekap->where('periode_id', $idAkhir), 'pagu'));
        } else {
            $akhirTanggal = $akhirPeriode?->tanggal_selesai->copy()->endOfDay()->toDateTimeString() ?? now()->toDateTimeString();
            $dicairkan = $p->akunId !== null ? [] : self::jumlah(DB::table('pencairan')->join('pengajuan', 'pengajuan.id', '=', 'pencairan.pengajuan_id')
                ->whereIn('pencairan.periode_id', $dalamRentang->pluck('id'))->where('pencairan.status', StatusPencairan::Dicatat->value)
                ->when($unitDipakai, fn (Builder $q) => $q->whereIn('pengajuan.unit_id', $unitDipakai)), 'pencairan.jumlah');
            $realisasi = self::jumlah(self::realisasiDasar($p, $dalamRentang->pluck('id')->all(), $unitDipakai), 'realisasi_item.jumlah_bruto');
            $kumulatif = self::jumlah(self::realisasiDasar($p, $sampaiAkhir->pluck('id')->all(), $unitDipakai), 'realisasi_item.jumlah_bruto');
            $disetujui = $p->akunId === null
                ? self::jumlah(DB::table('pengajuan')->where('tahun_anggaran_id', $p->tahunAnggaranId)->whereNull('deleted_at')
                    ->whereIn('status', [StatusPengajuan::Disetujui->value, StatusPengajuan::Dicairkan->value, StatusPengajuan::Selesai->value])
                    ->where('disetujui_pada', '<=', $akhirTanggal)
                    ->when($unitDipakai, fn (Builder $q) => $q->whereIn('unit_id', $unitDipakai)), 'pengajuan.total_disetujui')
                : self::jumlah(DB::table('rab_item')->join('pengajuan', 'pengajuan.rab_aktif_id', '=', 'rab_item.rab_id')
                    ->where('pengajuan.tahun_anggaran_id', $p->tahunAnggaranId)->whereNull('pengajuan.deleted_at')
                    ->whereIn('pengajuan.status', [StatusPengajuan::Disetujui->value, StatusPengajuan::Dicairkan->value, StatusPengajuan::Selesai->value])
                    ->where('pengajuan.disetujui_pada', '<=', $akhirTanggal)->where('rab_item.akun_id', $p->akunId)
                    ->when($unitDipakai, fn (Builder $q) => $q->whereIn('pengajuan.unit_id', $unitDipakai)), 'rab_item.jumlah');
        }

        $unitIds = collect([array_keys($pagu), array_keys($disetujui), array_keys($dicairkan), array_keys($kumulatif)])->flatten()->unique()->values();
        $unit = Unit::withTrashed()->whereIn('id', $unitIds)->get()->keyBy('id');
        $hitungSisa = app(HitungSisaPagu::class);
        $nol = '0.00';

        return $unitIds
            ->map(function (string $id) use ($p, $pagu, $disetujui, $dicairkan, $realisasi, $kumulatif, $unit, $hitungSisa, $snapshot, $nol): array {
                $paguUnit = $pagu[$id] ?? $nol;
                $komitmen = $p->akunId === null ? ($hitungSisa->handle($p->tahunAnggaranId, $id)['komitmen'] ?? $nol) : $nol;
                $kum = $kumulatif[$id] ?? $nol;

                return [
                    'unit_id' => $id,
                    'unit_kode' => (string) ($unit[$id]->kode ?? '-'),
                    'unit_nama' => (string) ($unit[$id]->nama ?? '-'),
                    'pagu' => $paguUnit,
                    'komitmen' => $komitmen,
                    'disetujui' => $disetujui[$id] ?? $nol,
                    'dicairkan' => $dicairkan[$id] ?? $nol,
                    'realisasi' => $realisasi[$id] ?? $nol,
                    'realisasi_kumulatif' => $kum,
                    'sisa_pagu' => Uang::kurang($paguUnit, $komitmen),
                    'serapan_persen' => self::serapan($kum, $paguUnit),
                    'sumber' => $snapshot ? 'snapshot' : 'langsung',
                ];
            })
            ->sortBy('unit_kode')
            ->values();
    }

    /**
     * @param  list<string>|null  $unitIds
     * @return array<string, string>
     */
    private static function paguPerUnit(ParameterLaporan $p, ?array $unitIds): array
    {
        if ($p->akunId !== null) {
            return self::jumlah(DB::table('pagu_akun')->join('pagu', 'pagu.id', '=', 'pagu_akun.pagu_id')
                ->where('pagu.tahun_anggaran_id', $p->tahunAnggaranId)->where('pagu.status', StatusPagu::Ditetapkan->value)->whereNull('pagu.deleted_at')
                ->where('pagu_akun.akun_id', $p->akunId)
                ->when($unitIds, fn (Builder $q) => $q->whereIn('pagu.unit_id', $unitIds)), 'pagu_akun.jumlah', 'pagu.unit_id');
        }

        return self::jumlah(DB::table('pagu')->where('tahun_anggaran_id', $p->tahunAnggaranId)->where('status', StatusPagu::Ditetapkan->value)->whereNull('deleted_at')
            ->when($unitIds, fn (Builder $q) => $q->whereIn('unit_id', $unitIds)), 'pagu.jumlah', 'pagu.unit_id');
    }

    /**
     * @param  list<string>  $periodeIds
     * @param  list<string>|null  $unitIds
     */
    private static function realisasiDasar(ParameterLaporan $p, array $periodeIds, ?array $unitIds): Builder
    {
        return DB::table('realisasi_item')
            ->join('spj', 'spj.id', '=', 'realisasi_item.spj_id')
            ->join('pengajuan', 'pengajuan.id', '=', 'spj.pengajuan_id')
            ->whereNull('realisasi_item.deleted_at')
            ->whereIn('realisasi_item.periode_id', $periodeIds)
            ->when($p->akunId, fn (Builder $q) => $q->join('rab_item', 'rab_item.id', '=', 'realisasi_item.rab_item_id')->where('rab_item.akun_id', $p->akunId))
            ->when($unitIds, fn (Builder $q) => $q->whereIn('pengajuan.unit_id', $unitIds));
    }

    /**
     * SUM per unit → peta unit_id ⇒ string desimal.
     *
     * @return array<string, string>
     */
    private static function jumlah(Builder $query, string $kolom, string $kolomUnit = 'pengajuan.unit_id'): array
    {
        $hasil = [];

        foreach ($query->groupBy($kolomUnit)->selectRaw("{$kolomUnit} AS unit_id, SUM({$kolom}) AS n")->get() as $baris) {
            $hasil[(string) $baris->unit_id] = Uang::jumlahkan([(string) ($baris->n ?? '0')]);
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, \stdClass>  $rekap
     * @return array<string, string>
     */
    private static function petakan(Collection $rekap, string $kolom): array
    {
        $hasil = [];

        foreach ($rekap->groupBy('unit_id') as $unitId => $baris) {
            $hasil[(string) $unitId] = Uang::jumlahkan($baris->pluck($kolom)->map(fn ($n): string => (string) $n)->all());
        }

        return $hasil;
    }
}
