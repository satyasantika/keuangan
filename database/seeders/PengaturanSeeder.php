<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use App\Support\Pengaturan as PengaturanAplikasi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Nilai awal pengaturan. Nilai yang sudah ada tidak ditimpa.
 */
class PengaturanSeeder extends Seeder
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}> kunci => [nilai, tipe, keterangan]
     */
    public static function bawaan(): array
    {
        return [
            'batas_hari_spj' => ['14', 'int', 'Batas hari kalender SPJ setelah kegiatan/pencairan (perlu verifikasi kebijakan)'],
            'hari_pengingat_spj' => ['3,0', 'string', 'Hari sebelum batas SPJ untuk mengirim pengingat (dipisah koma)'],
            'interval_pengingat_terlambat' => ['3', 'int', 'Interval hari pengingat SPJ terlambat'],
            'wajib_verifikasi_kepala_unit' => ['1', 'bool', 'Tahap verifikasi kepala unit aktif (BR-06)'],
            'wajib_persetujuan_wadek' => ['1', 'bool', 'Tahap persetujuan wadek keuangan aktif (BR-06)'],
            'pola_pengelolaan' => ['satker', 'string', 'satker atau blu — hanya konfigurasi (BR-25, perlu verifikasi status Unsil)'],
            'format_nomor_pengajuan' => ['PGJ/{tahun}/{urut}', 'string', 'Format nomor pengajuan (BR-05)'],
            'format_nomor_spj' => ['SPJ/{tahun}/{urut}', 'string', 'Format nomor SPJ'],
            'honor_mengurangi_pagu' => ['0', 'bool', 'Rekap honor fkipapp mengurangi pagu (BR-24)'],
            'masa_berlaku_ekspor_jam' => ['24', 'int', 'Masa berlaku berkas ekspor sementara (jam)'],
            'tanggal_pengingat_kunci' => ['5', 'int', 'Tanggal pengingat kunci periode bulan lalu'],
            'nama_fakultas' => ['Fakultas Keguruan dan Ilmu Pendidikan', 'string', 'Nama fakultas pada dokumen'],
            'jabatan_penyetuju_akhir' => ['Wakil Dekan Bidang Keuangan (sesuaikan)', 'string', 'Jabatan penyetuju akhir pada dokumen'],
        ];
    }

    public function run(): void
    {
        foreach (self::bawaan() as $kunci => [$nilai, $tipe, $keterangan]) {
            Pengaturan::firstOrCreate(['kunci' => $kunci], ['nilai' => $nilai, 'tipe' => $tipe, 'keterangan' => $keterangan]);
        }

        Cache::forget(PengaturanAplikasi::KUNCI_CACHE);
    }
}
