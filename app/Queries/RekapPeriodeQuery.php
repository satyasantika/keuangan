<?php

namespace App\Queries;

use App\Enums\StatusPagu;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPengembalian;
use App\Models\Periode;
use App\Support\Uang;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka rekap per unit (dan per akun) untuk satu periode. Agregasi SUM di SQL, hasil sebagai string desimal.
 */
class RekapPeriodeQuery
{
    /**
     * @return Collection<string, array{unit_id: string, pagu: string, total_disetujui_kumulatif: string, total_pencairan: string, total_realisasi: string, total_pajak: string, total_pengembalian: string, akun: array<array{total_disetujui_kumulatif: string, total_realisasi: string}>}>
     */
    public static function untuk(Periode $periode): Collection
    {
        $tahunId = $periode->tahun_anggaran_id;
        $akhir = $periode->tanggal_selesai->copy()->endOfDay()->toDateTimeString();
        $statusDisetujui = [StatusPengajuan::Disetujui->value, StatusPengajuan::Dicairkan->value, StatusPengajuan::Selesai->value];

        $pagu = self::peta(DB::table('pagu')->where('tahun_anggaran_id', $tahunId)->where('status', StatusPagu::Ditetapkan->value)->whereNull('deleted_at')
            ->groupBy('unit_id')->selectRaw('unit_id, SUM(jumlah) AS n'));

        $disetujui = self::peta(DB::table('pengajuan')->where('tahun_anggaran_id', $tahunId)->whereNull('deleted_at')
            ->whereNotNull('disetujui_pada')->where('disetujui_pada', '<=', $akhir)->whereIn('status', $statusDisetujui)
            ->groupBy('unit_id')->selectRaw('unit_id, SUM(total_disetujui) AS n'));

        $pencairan = self::peta(DB::table('pencairan')->join('pengajuan', 'pengajuan.id', '=', 'pencairan.pengajuan_id')
            ->where('pencairan.periode_id', $periode->id)->where('pencairan.status', StatusPencairan::Dicatat->value)
            ->groupBy('pengajuan.unit_id')->selectRaw('pengajuan.unit_id, SUM(pencairan.jumlah) AS n'));

        $realisasiDasar = fn () => DB::table('realisasi_item')
            ->join('spj', 'spj.id', '=', 'realisasi_item.spj_id')
            ->join('pengajuan', 'pengajuan.id', '=', 'spj.pengajuan_id')
            ->where('realisasi_item.periode_id', $periode->id)
            ->whereNull('realisasi_item.deleted_at');

        $realisasi = self::peta($realisasiDasar()->groupBy('pengajuan.unit_id')->selectRaw('pengajuan.unit_id, SUM(realisasi_item.jumlah_bruto) AS n'));

        $pajak = self::peta($realisasiDasar()->join('potongan_pajak', 'potongan_pajak.realisasi_item_id', '=', 'realisasi_item.id')
            ->groupBy('pengajuan.unit_id')->selectRaw('pengajuan.unit_id, SUM(potongan_pajak.nilai) AS n'));

        $pengembalian = self::peta(DB::table('pengembalian')->join('pengajuan', 'pengajuan.id', '=', 'pengembalian.pengajuan_id')
            ->where('pengembalian.periode_id', $periode->id)->where('pengembalian.status', StatusPengembalian::Diverifikasi->value)
            ->groupBy('pengajuan.unit_id')->selectRaw('pengajuan.unit_id, SUM(pengembalian.jumlah) AS n'));

        $akunDisetujui = DB::table('rab_item')
            ->join('pengajuan', 'pengajuan.rab_aktif_id', '=', 'rab_item.rab_id')
            ->where('pengajuan.tahun_anggaran_id', $tahunId)->whereNull('pengajuan.deleted_at')
            ->whereNotNull('pengajuan.disetujui_pada')->where('pengajuan.disetujui_pada', '<=', $akhir)->whereIn('pengajuan.status', $statusDisetujui)
            ->groupBy('pengajuan.unit_id', 'rab_item.akun_id')
            ->selectRaw('pengajuan.unit_id, rab_item.akun_id, SUM(rab_item.jumlah) AS n')->get();

        $akunRealisasi = $realisasiDasar()->join('rab_item', 'rab_item.id', '=', 'realisasi_item.rab_item_id')
            ->groupBy('pengajuan.unit_id', 'rab_item.akun_id')
            ->selectRaw('pengajuan.unit_id, rab_item.akun_id, SUM(realisasi_item.jumlah_bruto) AS n')->get();

        $unitIds = collect([$pagu, $disetujui, $pencairan, $realisasi, $pajak, $pengembalian])->flatMap(fn (array $p) => array_keys($p))->unique()->values();
        $nol = '0.00';

        return $unitIds->mapWithKeys(function (string $unitId) use ($pagu, $disetujui, $pencairan, $realisasi, $pajak, $pengembalian, $akunDisetujui, $akunRealisasi, $nol): array {
            $akun = [];

            foreach ($akunDisetujui->where('unit_id', $unitId) as $baris) {
                $akun[$baris->akun_id]['total_disetujui_kumulatif'] = Uang::jumlahkan([(string) $baris->n]);
            }

            foreach ($akunRealisasi->where('unit_id', $unitId) as $baris) {
                $akun[$baris->akun_id]['total_realisasi'] = Uang::jumlahkan([(string) $baris->n]);
            }

            foreach ($akun as $id => $nilai) {
                $akun[$id] = ['total_disetujui_kumulatif' => $nilai['total_disetujui_kumulatif'] ?? $nol, 'total_realisasi' => $nilai['total_realisasi'] ?? $nol];
            }

            return [$unitId => [
                'unit_id' => $unitId,
                'pagu' => $pagu[$unitId] ?? $nol,
                'total_disetujui_kumulatif' => $disetujui[$unitId] ?? $nol,
                'total_pencairan' => $pencairan[$unitId] ?? $nol,
                'total_realisasi' => $realisasi[$unitId] ?? $nol,
                'total_pajak' => $pajak[$unitId] ?? $nol,
                'total_pengembalian' => $pengembalian[$unitId] ?? $nol,
                'akun' => $akun,
            ]];
        });
    }

    /**
     * @return array<string, string> unit_id => jumlah
     */
    private static function peta(Builder $query): array
    {
        $hasil = [];

        foreach ($query->get() as $baris) {
            $hasil[(string) $baris->unit_id] = Uang::jumlahkan([(string) ($baris->n ?? '0')]);
        }

        return $hasil;
    }
}
