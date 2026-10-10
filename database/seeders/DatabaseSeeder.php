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
        ]);

        // Data CONTOH hanya untuk lokal/staging.
        if (app()->environment(['local', 'staging'])) {
            $this->call([
                UnitContohSeeder::class,
            ]);
        }
    }
}
