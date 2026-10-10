<?php

namespace Database\Seeders;

use App\Enums\JenisUnit;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * CONTOH — ganti dengan daftar resmi unit FKIP (ADR 0002 parameter 9).
 * Hanya untuk lokal/staging; daftar resmi diimpor lewat UnitImporter.
 */
class UnitContohSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            ['kode' => 'DEKANAT', 'nama' => 'Dekanat FKIP', 'jenis' => JenisUnit::Pimpinan, 'jenjang' => null],
            ['kode' => 'TU-FKIP', 'nama' => 'Tata Usaha FKIP', 'jenis' => JenisUnit::UnitPendukung, 'jenjang' => null],
            ['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'jenis' => JenisUnit::Prodi, 'jenjang' => 'S1'],
            ['kode' => 'PBIO', 'nama' => 'Pendidikan Biologi', 'jenis' => JenisUnit::Prodi, 'jenjang' => 'S1'],
            ['kode' => 'PBI', 'nama' => 'Pendidikan Bahasa Inggris', 'jenis' => JenisUnit::Prodi, 'jenjang' => 'S1'],
        ];

        foreach ($daftar as $unit) {
            Unit::updateOrCreate(['kode' => $unit['kode']], $unit);
        }
    }
}
