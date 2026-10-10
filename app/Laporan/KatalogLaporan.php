<?php

namespace App\Laporan;

use App\Data\ParameterLaporan;
use App\Enums\Peran;
use App\Models\Pengajuan;
use App\Models\Periode;
use App\Models\RealisasiItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Queries\LaporanRealisasiQuery;
use App\Queries\PenggunaanDanaQuery;
use App\Queries\RekapHonorQuery;
use App\Queries\RekapPajakQuery;
use App\Queries\RekapPengajuanQuery;
use App\Queries\RekapPengembalianQuery;
use App\Queries\SnapshotPeriodeQuery;
use App\Queries\SpjTerlambatQuery;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Daftar laporan, hak akses per laporan, dan penyusunan isinya dari Queries.
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
            'LAP-03' => 'LAP-03 Kartu Kegiatan (PDF)',
            'LAP-04' => 'LAP-04 SPJ Terlambat / Jatuh Tempo',
            'LAP-05' => 'LAP-05 Rekap Potongan Pajak',
            'LAP-06' => 'LAP-06 Rekap Pengembalian Sisa Dana',
            'LAP-07' => 'LAP-07 Snapshot Periode Terkunci',
            'LAP-08' => 'LAP-08 Penggunaan Dana per Prodi (Akreditasi)',
            'LAP-09' => 'LAP-09 Rekap Honor Ujian (impor fkipapp)',
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
            'LAP-03' => $pengguna->can('pengajuan.lihat'),
            'LAP-04' => $pengguna->hasAnyRole([Peran::AdminKeuangan->value, Peran::Bpp->value, Peran::KepalaUnit->value]),
            'LAP-05', 'LAP-06' => $pengguna->hasAnyRole([Peran::AdminKeuangan->value, Peran::Bpp->value]),
            'LAP-07' => $pengguna->hasAnyRole([Peran::AdminKeuangan->value, Peran::WadekKeuangan->value]),
            'LAP-08' => $pengguna->hasAnyRole([Peran::AdminKeuangan->value, Peran::KepalaUnit->value]),
            'LAP-09' => $pengguna->can('impor.lihat'),
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
            'LAP-03' => self::kartuKegiatan($parameter, $pengguna),
            'LAP-04' => self::spjTerlambat($tahun, $pengguna),
            'LAP-05' => self::rekapPajak($tahun, $pengguna),
            'LAP-06' => self::rekapPengembalian($tahun, $pengguna),
            'LAP-07' => self::snapshot($tahun, $parameter, $pengguna),
            'LAP-08' => self::penggunaanDana($tahun, $parameter, $pengguna),
            'LAP-09' => self::rekapHonor($tahun, $parameter, $pengguna),
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
        $honor = LaporanRealisasiQuery::honorUjian($param, $pengguna);
        $catatan = $snapshot ? ['Angka periode terkunci berasal dari snapshot saat periode dikunci.'] : [];

        if (Uang::bandingkan($honor, '0') > 0) {
            $catatan[] = 'Honor ujian (impor fkipapp): '.Uang::format($honor).' — rekap baca-saja, tidak termasuk realisasi kegiatan di atas.';
        }

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
            catatan: $catatan,
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
     * Ringkasan tabel LAP-03 (PDF lengkapnya lewat route pengajuan.kartu).
     *
     * @param  array<string, mixed>  $parameter
     */
    private static function kartuKegiatan(array $parameter, User $pengguna): DataLaporan
    {
        $pengajuan = Pengajuan::query()->with(['unit', 'rabAktif.item'])->find((string) ($parameter['pengajuan_id'] ?? ''));

        if ($pengajuan === null) {
            throw new InvalidArgumentException('Pilih pengajuan untuk LAP-03.');
        }

        if (! $pengguna->can('view', $pengajuan)) {
            throw new AuthorizationException('Anda tidak berhak melihat pengajuan ini.');
        }

        $baris = [];

        foreach ($pengajuan->rabAktif->item ?? [] as $item) {
            $terealisasi = Uang::jumlahkan(RealisasiItem::query()->where('rab_item_id', $item->id)->pluck('jumlah_bruto')->map(fn ($j): string => (string) $j)->all());
            $baris[] = [$item->uraian, (string) $item->jumlah, $terealisasi, Uang::kurang((string) $item->jumlah, $terealisasi)];
        }

        return new DataLaporan(
            kode: 'LAP-03',
            judul: 'Kartu Kegiatan',
            subjudul: "{$pengajuan->nomor} — {$pengajuan->judul_kegiatan} ({$pengajuan->unit->kode})",
            kolom: ['Item RAB aktif', 'Jumlah RAB', 'Terealisasi', 'Sisa'],
            baris: $baris,
            total: ['TOTAL', (string) ($pengajuan->rabAktif->total ?? '0.00'), (string) $pengajuan->total_realisasi, Uang::kurang((string) ($pengajuan->rabAktif->total ?? '0'), (string) $pengajuan->total_realisasi)],
            kolomUang: [1, 2, 3],
        );
    }

    private static function spjTerlambat(TahunAnggaran $tahun, User $pengguna): DataLaporan
    {
        $data = SpjTerlambatQuery::untuk($pengguna, $tahun->id);

        return new DataLaporan(
            kode: 'LAP-04',
            judul: 'Daftar SPJ Terlambat / Jatuh Tempo',
            subjudul: $tahun->nama.' — per '.now()->format('d-m-Y'),
            kolom: ['Unit', 'Nomor', 'Judul kegiatan', 'Batas SPJ', 'Hari terlambat', 'Status SPJ'],
            baris: $data->map(fn (array $b): array => [$b['unit'], $b['nomor'], $b['judul'], $b['batas_spj'], $b['hari_terlambat'], $b['status_spj']])->values()->all(),
        );
    }

    private static function rekapPajak(TahunAnggaran $tahun, User $pengguna): DataLaporan
    {
        $data = RekapPajakQuery::untuk($pengguna, $tahun->id);

        return new DataLaporan(
            kode: 'LAP-05',
            judul: 'Rekap Potongan Pajak (data informasi; tarif referensi perlu verifikasi)',
            subjudul: $tahun->nama,
            kolom: ['Bulan', 'Jenis pajak', 'Unit', 'Nilai', 'Jumlah bukti', 'Dengan bukti setor', 'Tanpa bukti setor'],
            baris: $data->map(fn (array $b): array => [$b['bulan'], $b['jenis'], $b['unit'], $b['nilai'], $b['jumlah_bukti'], $b['dengan_bukti_setor'], $b['tanpa_bukti_setor']])->values()->all(),
            total: ['TOTAL', '', '', Uang::jumlahkan($data->pluck('nilai')->all()), $data->sum('jumlah_bukti'), $data->sum('dengan_bukti_setor'), $data->sum('tanpa_bukti_setor')],
            kolomUang: [3],
            catatan: ['Data informasi; sistem tidak menghitung pajak (BR-16). Tarif referensi perlu verifikasi.'],
        );
    }

    private static function rekapPengembalian(TahunAnggaran $tahun, User $pengguna): DataLaporan
    {
        $data = RekapPengembalianQuery::untuk($pengguna, $tahun->id);

        return new DataLaporan(
            kode: 'LAP-06',
            judul: 'Rekap Pengembalian Sisa Dana',
            subjudul: $tahun->nama,
            kolom: ['Bulan', 'Unit', 'Jumlah setoran', 'Banyak setoran'],
            baris: $data->map(fn (array $b): array => [$b['bulan'], $b['unit'], $b['jumlah'], $b['banyak']])->values()->all(),
            total: ['TOTAL', '', Uang::jumlahkan($data->pluck('jumlah')->all()), $data->sum('banyak')],
            kolomUang: [2],
        );
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private static function penggunaanDana(TahunAnggaran $tahun, array $parameter, User $pengguna): DataLaporan
    {
        $data = PenggunaanDanaQuery::untuk($pengguna, $tahun->tahun, filled($parameter['unit_id'] ?? null) ? (string) $parameter['unit_id'] : null);

        return new DataLaporan(
            kode: 'LAP-08',
            judul: 'Penggunaan Dana per Prodi (format instrumen akreditasi perlu verifikasi)',
            subjudul: 'TS = '.$tahun->tahun,
            kolom: ['Prodi', 'Kategori penggunaan dana', 'TS-2 ('.($tahun->tahun - 2).')', 'TS-1 ('.($tahun->tahun - 1).')', 'TS ('.$tahun->tahun.')', 'Rata-rata'],
            baris: $data->map(fn (array $b): array => [$b['unit'], $b['kategori'], $b['ts2'], $b['ts1'], $b['ts'], $b['rata_rata']])->values()->all(),
            kolomUang: [2, 3, 4, 5],
            catatan: ['Format instrumen akreditasi (LKPS/LAMDIK) perlu verifikasi.'],
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

    /**
     * @param  array<string, mixed>  $parameter
     */
    private static function rekapHonor(TahunAnggaran $tahun, array $parameter, User $pengguna): DataLaporan
    {
        $param = self::parameterLaporan($tahun, $parameter);
        $data = RekapHonorQuery::untuk($param, $pengguna);

        return new DataLaporan(
            kode: 'LAP-09',
            judul: 'Rekap Honor Ujian (impor fkipapp)',
            subjudul: "{$tahun->nama} — {$param->label()}",
            kolom: ['Bulan', 'Unit', 'Jenis ujian', 'Jumlah kegiatan', 'Bruto', 'Pajak', 'Neto'],
            baris: $data->map(fn (array $b): array => [$b['bulan'], $b['unit'], $b['jenis_ujian'], $b['jumlah_kegiatan'], $b['bruto'], $b['pajak'], $b['neto']])->values()->all(),
            total: ['TOTAL', '', '', $data->sum('jumlah_kegiatan'), Uang::jumlahkan($data->pluck('bruto')->all()), Uang::jumlahkan($data->pluck('pajak')->all()), Uang::jumlahkan($data->pluck('neto')->all())],
            kolomUang: [4, 5, 6],
            catatan: ['Rekap total dari fkipapp; honor ujian tidak dihitung di sistem ini (BR-24).'],
        );
    }
}
