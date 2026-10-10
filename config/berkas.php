<?php

/*
| Kebijakan berkas = tautan, bukan unggahan (STANDAR-TEKNIS §1a, BR-15).
*/
return [

    'mode' => env('BERKAS_MODE', 'tautan'),

    /*
    | Host yang diizinkan. Awalan "*." mencocokkan subdomain (dan domain itu sendiri).
    | Opsional bila diputuskan kelak: 'onedrive.live.com', '*.sharepoint.com'.
    */
    'domain_diizinkan' => [
        'drive.google.com',
        'docs.google.com',
        '*.unsil.ac.id',
    ],

    'pemendek_ditolak' => [
        'bit.ly', 's.id', 'tinyurl.com', 't.co', 'goo.gl', 'cutt.ly', 'shorturl.at', 'rebrand.ly',
    ],

    'panjang_maks' => 2048,

];
