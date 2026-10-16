<?php

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): contoh env tidak boleh kembali ke Redis/Horizon.
 */
dataset('contoh env', ['.env.example', '.env.production.example']);

it('memakai driver database pada contoh env', function (string $berkas) {
    $isi = file_get_contents(dirname(__DIR__, 2).'/'.$berkas);

    expect($isi)
        ->toContain("\nSESSION_DRIVER=database\n")
        ->toContain("\nQUEUE_CONNECTION=database\n")
        ->toContain("\nCACHE_STORE=database\n")
        ->toContain("\nKEUANGAN_LOCK_STORE=database\n")
        ->toContain("\nAPP_KODE=keuangan\n")
        ->not->toMatch('/^(REDIS|HORIZON)_/m')
        ->not->toContain('_DRIVER=redis')
        ->not->toContain('QUEUE_CONNECTION=redis')
        ->not->toContain('CACHE_STORE=redis')
        ->not->toContain('KEUANGAN_LOCK_STORE=redis');
})->with('contoh env');
