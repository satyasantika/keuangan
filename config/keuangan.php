<?php

return [

    /*
    | Akun super-admin pertama untuk PenggunaAwalSeeder (diisi lewat .env).
    */
    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
        'nama' => env('SEED_ADMIN_NAMA', 'Admin TI Fakultas'),
    ],

];
