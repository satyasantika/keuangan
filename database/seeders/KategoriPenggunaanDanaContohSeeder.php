<?php

namespace Database\Seeders;

use App\Models\KategoriPenggunaanDana;
use Illuminate\Database\Seeder;

/**
 * CONTOH — format instrumen akreditasi (LKPS/LAMDIK) perlu verifikasi (ADR 0002 parameter 10).
 */
class KategoriPenggunaanDanaContohSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            'operasional-pendidikan' => 'Biaya operasional pendidikan',
            'operasional-kemahasiswaan' => 'Biaya operasional kemahasiswaan',
            'penelitian' => 'Penelitian',
            'pkm' => 'Pengabdian kepada masyarakat',
            'investasi-sdm' => 'Investasi SDM',
            'investasi-sarana' => 'Investasi sarana',
            'investasi-prasarana' => 'Investasi prasarana',
        ];

        $urutan = 1;

        foreach ($daftar as $kode => $nama) {
            KategoriPenggunaanDana::withTrashed()->firstOrCreate(['kode' => $kode], ['nama' => $nama, 'urutan' => $urutan]);
            $urutan++;
        }
    }
}
