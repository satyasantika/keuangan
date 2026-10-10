<?php

namespace Database\Seeders;

use App\Models\JenisPencairan;
use Illuminate\Database\Seeder;

/**
 * Istilah UP/TUP/LS di tingkat fakultas perlu verifikasi (ADR 0002 parameter 3).
 */
class JenisPencairanSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            'UP' => 'Uang Persediaan',
            'TUP' => 'Tambahan Uang Persediaan',
            'LS' => 'Pembayaran Langsung',
        ];

        foreach ($daftar as $kode => $nama) {
            JenisPencairan::withTrashed()->firstOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'pola' => 'semua', 'perlu_verifikasi' => true, 'keterangan' => 'Istilah dan penerapan di fakultas perlu verifikasi'],
            );
        }
    }
}
