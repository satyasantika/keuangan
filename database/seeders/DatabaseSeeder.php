<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder yang boleh berjalan di production (rilis 1.0.0): peran & izin, super-admin pertama, pengaturan,
     * serta master jenis pencairan & jenis pajak.
     */
    public const PRODUKSI = [
        PeranDanIzinSeeder::class,
        PenggunaAwalSeeder::class,
        PengaturanSeeder::class,
        JenisPencairanSeeder::class,
        JenisPajakSeeder::class,
    ];

    /**
     * Data CONTOH (tarif pajak, kategori akreditasi, akun BAS) — tidak dijalankan di production.
     */
    public const CONTOH = [
        TarifPajakContohSeeder::class,
        KategoriPenggunaanDanaContohSeeder::class,
        AkunContohSeeder::class,
    ];

    public function run(): void
    {
        $this->call(self::PRODUKSI);

        if (app()->isProduction()) {
            $this->command->warn('Data contoh (tarif pajak, kategori penggunaan dana, akun BAS) dilewati di production. '
                .'Isi data resmi sesuai ADR 0002 lewat panel: Master → Jenis & Tarif Pajak, Kategori Penggunaan Dana, Akun BAS (Impor).');

            return;
        }

        $this->call(self::CONTOH);

        // Data CONTOH hanya untuk lokal/staging.
        if (app()->environment(['local', 'staging'])) {
            $this->call([
                UnitContohSeeder::class,
            ]);
        }

        // Pengguna & transaksi demo hanya di lokal.
        if (app()->environment('local')) {
            $this->call([DemoSeeder::class]);
        }
    }
}
