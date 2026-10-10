<?php

return [

    /*
    | Store cache untuk lock aksi (DenganKunci). Produksi: redis; test: array.
    */
    'lock_store' => env('KEUANGAN_LOCK_STORE', 'redis'),

    /*
    | Reverse proxy tepercaya: "*" (lokal) atau daftar IP dipisah koma (produksi).
    | Dibaca lewat config karena .env belum dimuat saat bootstrap/app.php dievaluasi.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    | Akun super-admin pertama untuk PenggunaAwalSeeder (diisi lewat .env).
    */
    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL', 'fkip@unsil.ac.id'),
        'password' => env('SEED_ADMIN_PASSWORD', 'siliwangi'),
        'nama' => env('SEED_ADMIN_NAMA', 'Admin TI Fakultas'),
    ],

    /*
    | Kewajiban MFA (autentikator) bagi super-admin, admin-keuangan, bpp, dan wadek-keuangan.
    | Bawaan nonaktif agar akun langsung dapat dipakai; MFA tetap dapat diaktifkan sukarela dari halaman profil.
    | Aktifkan dengan MFA_WAJIB=true bila sudah siap.
    */
    'mfa_wajib' => (bool) env('MFA_WAJIB', false),

    /*
    | Data UAT (UatSeeder, hanya APP_ENV=staging): kata sandi awal & pemetaan akun → surel penguji.
    */
    'uat' => [
        'password' => env('UAT_PASSWORD'),
        'emails' => env('UAT_EMAILS', ''),
    ],

    /*
    | Akun demo panduan (PanduanSeeder, hanya APP_ENV=local) & tangkapan layar Playwright.
    */
    'panduan' => [
        'password' => env('PANDUAN_PASSWORD'),
    ],

];
