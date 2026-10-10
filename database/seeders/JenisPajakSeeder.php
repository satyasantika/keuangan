<?php

namespace Database\Seeders;

use App\Models\JenisPajak;
use Illuminate\Database\Seeder;

class JenisPajakSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            'pph21' => 'PPh Pasal 21',
            'pph22' => 'PPh Pasal 22',
            'pph23' => 'PPh Pasal 23',
            'pph4_2' => 'PPh Pasal 4 ayat (2)',
            'ppn' => 'PPN',
        ];

        foreach ($daftar as $kode => $nama) {
            JenisPajak::withTrashed()->firstOrCreate(['kode' => $kode], ['nama' => $nama]);
        }
    }
}
