<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PeranDanIzinSeeder::class,
            PenggunaAwalSeeder::class,
            KategoriPenggunaanDanaContohSeeder::class,
            // Menjaga dirinya sendiri: di produksi dilewati bila akun hasil impor sudah ada.
            AkunContohSeeder::class,
        ]);

        // Data CONTOH hanya untuk lokal/staging.
        if (app()->environment(['local', 'staging'])) {
            $this->call([
                UnitContohSeeder::class,
            ]);
        }
    }
}
