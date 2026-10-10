<?php

namespace Database\Seeders;

use App\Models\Akun;
use Illuminate\Database\Seeder;

/**
 * CONTOH akun BAS (sumber=contoh) — wajib diganti lewat impor BAS resmi.
 * Kode & nama perlu verifikasi terhadap BAS yang berlaku (ADR 0002 parameter 8).
 */
class AkunContohSeeder extends Seeder
{
    public const KETERANGAN = 'CONTOH — perlu verifikasi terhadap BAS yang berlaku';

    public function run(): void
    {
        if (app()->isProduction() && Akun::query()->where('sumber', 'impor')->exists()) {
            return;
        }

        $daftar = [
            '521211' => ['Belanja Bahan', 'barang'],
            '521213' => ['Belanja Honor Output Kegiatan', 'barang'],
            '521219' => ['Belanja Barang Non Operasional Lainnya', 'barang'],
            '522151' => ['Belanja Jasa Profesi', 'barang'],
            '524111' => ['Belanja Perjalanan Dinas Biasa', 'barang'],
            '524113' => ['Belanja Perjalanan Dinas Dalam Kota', 'barang'],
        ];

        foreach ($daftar as $kode => [$nama, $jenis]) {
            $akun = Akun::withTrashed()->firstOrNew(['kode' => (string) $kode]);

            // Akun yang sudah diimpor/diisi manual tidak ditimpa contoh.
            if ($akun->exists && $akun->sumber !== 'contoh') {
                continue;
            }

            $akun->fill([
                'nama' => $nama,
                'kelompok' => substr((string) $kode, 0, 3),
                'jenis_belanja' => $jenis,
                'sumber' => 'contoh',
                'keterangan' => self::KETERANGAN,
            ])->save();
        }
    }
}
