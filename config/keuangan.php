<?php

return [

    /*
    | Store cache untuk lock aksi (DenganKunci). Produksi: redis; test: array.
    */
    'lock_store' => env('KEUANGAN_LOCK_STORE', 'redis'),

    /*
    | Reverse proxy tepercaya (STANDAR-TEKNIS §2.4): "*" (lokal) atau daftar IP dipisah koma (produksi).
    | Dibaca lewat config karena .env belum dimuat saat bootstrap/app.php dievaluasi.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    | Akun super-admin pertama untuk PenggunaAwalSeeder (diisi lewat .env).
    */
    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
        'nama' => env('SEED_ADMIN_NAMA', 'Admin TI Fakultas'),
    ],

];
