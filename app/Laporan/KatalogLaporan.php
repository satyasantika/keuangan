<?php

namespace App\Laporan;

use App\Data\ParameterLaporan;
use App\Enums\Peran;
use App\Models\Periode;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Queries\LaporanRealisasiQuery;
use App\Queries\RekapPengajuanQuery;
use App\Queries\SnapshotPeriodeQuery;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Daftar laporan (01-PRD §8), hak akses per laporan, dan penyusunan isinya dari Queries.
 */
class KatalogLaporan
{
    public const BATAS_STREAM = 2000;

    /**
     * @return array<string, string> kode => label
     */
    public static function semua(): array
    {
        return [
            'LAP-01' => 'LAP-01 Realisasi Anggaran per Unit',
            'LAP-02' => 'LAP-02 Rekap Pengajuan per Status',
            'LAP-07' => 'LAP-07 Snapshot Periode Terkunci',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function untuk(User $pengguna): array
    {
        return array_filter(self::semua(), fn (string $label, string $kode): bool => self::bolehAkses($kode, $pengguna), ARRAY_FILTER_USE_BOTH);
    }

    public static function bolehAkses(string $kode, User $pengguna): bool
    {
        if (! $pengguna->can('laporan.ekspor')) {
            return false;
        }

        return match ($kode) {
            'LAP-01' => $pengguna->lingkupFakultas() || $pengguna->unit_id !== null,
            'LAP-02' => $pengguna->hasAnyRole([Peran::AdminKeuangan->value, Peran::Pimpinan->value]),
            'LAP-07' => $pengguna->hasAnyRole([Peran::AdminKeuangan->value, Peran::WadekKeuangan->value]),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $parameter  tahun_anggaran_id, jenis_periode, nilai_periode, unit_id
     */
    public static function bangun(string $kode, array $parameter, User $pengguna): DataLaporan
    {
        if (! array_key_exists($kode, self::semua())) {
            throw new InvalidArgumentException('Jenis laporan tidak dikenal.');
        }

        if (! self::bolehAkses($kode, $pengguna)) {
            throw new AuthorizationException('Anda tidak berhak mengakses laporan ini.');
        }

        $tahun = TahunAnggaran::query()->findOrFail((string) ($parameter['tahun_anggaran_id'] ?? ''));

        return match ($kode) {
            'LAP-01' => self::realisasi($tahun, $parameter, $pengguna),
            'LAP-02' => self::rekapPengajuan($tahun, $parameter, $pengguna),
            'LAP-07' => self::snapshot($tahun, $parameter, $pengguna),
            default => throw new InvalidArgumentException('Jenis laporan tidak dikenal.'),
        };
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    public static function parameterLaporan(TahunAnggaran $tahun, array $parameter): ParameterLaporan
    {
        $jenis = (string) ($parameter['jenis_periode'] ?? ParameterLaporan::TAHUNAN);
        $nilai = isset($parameter['nilai_periode']) && $parameter['nilai_periode'] !== '' ? (int) $parameter['nilai_periode'] : null;
        $unit = filled($parameter['unit_id'] ?? null) ? (string) $parameter['unit_id'] : null;

        return new ParameterLaporan($tahun->id, $jenis, $jenis === ParameterLaporan::TAHUNAN ? null : $nilai, $unit);
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private static function realisasi(TahunAnggaran $tahun, array $parameter, User $pengguna): DataLaporan
    {
        $param = self::parameterLaporan($tahun, $parameter);
        $data = LaporanRealisasiQuery::untuk($param, $pengguna);
        $total = LaporanRealisasiQuery::total($data);
        $snapshot = $data->contains('sumber', 'snapshot');

        return new DataLaporan(
            kode: 'LAP-01',
            judul: 'Laporan Realisasi Anggaran per Unit',
            subjudul: "{$tahun->nama} — {$param->label()}",
            kolom: ['Unit', 'Nama unit', 'Pagu', 'Komitmen', 'Disetujui', 'Dicairkan', 'Realisasi', 'Realisasi kumulatif', 'Sisa pagu', 'Serapan (%)'],
            baris: $data->map(fn (array $b): array => [
                $b['unit_kode'], $b['unit_nama'], $b['pagu'], $b['komitmen'], $b['disetujui'], $b['dicairkan'],
                $b['realisasi'], $b['realisasi_kumulatif'], $b['sisa_pagu'], $b['serapan_persen'],
            ])->values()->all(),
            total: ['TOTAL', '', $total['pagu'], $total['komitmen'], $total['disetujui'], $total['dicairkan'], $total['realisasi'], $total['realisasi_kumulatif'], $total['sisa_pagu'], $total['serapan_persen']],
            kolomUang: [2, 3, 4, 5, 6, 7, 8],
            catatan: $snapshot ? ['Angka periode terkunci berasal dari snapshot saat periode dikunci.'] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private static function rekapPengajuan(TahunAnggaran $tahun, array $parameter, User $pengguna): DataLaporan
    {
        $data = RekapPengajuanQuery::untuk($pengguna, $tahun->id, filled($parameter['unit_id'] ?? null) ? (string) $parameter['unit_id'] : null);

        return new DataLaporan(
            kode: 'LAP-02',
            judul: 'Rekap Pengajuan per Status',
            subjudul: $tahun->nama,
            kolom: ['Unit', 'Status', 'Jumlah pengajuan', 'Nilai diajukan', 'Nilai disetujui'],
            baris: $data->map(fn (array $b): array => [$b['unit'], $b['status'], $b['jumlah'], $b['nilai_diajukan'], $b['nilai_disetujui']])->values()->all(),
            total: ['TOTAL', '', $data->sum('jumlah'),
                Uang::jumlahkan($data->pluck('nilai_diajukan')->all()),
                Uang::jumlahkan($data->pluck('nilai_disetujui')->all())],
            kolomUang: [3, 4],
        );
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private static function snapshot(TahunAnggaran $tahun, array $parameter, User $pengguna): DataLaporan
    {
        $bulan = (int) ($parameter['nilai_periode'] ?? 0);
        $periode = Periode::query()->where('tahun_anggaran_id', $tahun->id)->where('bulan', $bulan)->first();

        if ($periode === null) {
            throw new InvalidArgumentException('Pilih bulan periode untuk LAP-07.');
        }

        $data = SnapshotPeriodeQuery::untuk($pengguna, $periode);
        $jumlah = fn (string $kolom): string => Uang::jumlahkan($data->map(fn ($r): string => (string) $r->getAttribute($kolom))->all());

        return new DataLaporan(
            kode: 'LAP-07',
            judul: 'Snapshot Periode Terkunci',
            subjudul: "{$periode->nama} — versi kunci {$periode->versi_kunci}".($periode->dikunci_pada ? ', dikunci '.$periode->dikunci_pada->format('d-m-Y H:i') : ''),
            kolom: ['Unit', 'Pagu', 'Disetujui kumulatif', 'Pencairan', 'Realisasi', 'Pajak', 'Pengembalian'],
            baris: $data->map(fn ($r): array => [
                $r->unit->kode, (string) $r->pagu, (string) $r->total_disetujui_kumulatif, (string) $r->total_pencairan,
                (string) $r->total_realisasi, (string) $r->total_pajak, (string) $r->total_pengembalian,
            ])->values()->all(),
            total: ['TOTAL', $jumlah('pagu'), $jumlah('total_disetujui_kumulatif'), $jumlah('total_pencairan'), $jumlah('total_realisasi'), $jumlah('total_pajak'), $jumlah('total_pengembalian')],
            kolomUang: [1, 2, 3, 4, 5, 6],
            catatan: ['Angka berasal dari snapshot saat periode dikunci.'],
        );
    }
}
