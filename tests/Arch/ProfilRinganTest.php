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

it('tidak memasang Horizon maupun klien Redis', function () {
    $akar = dirname(__DIR__, 2);
    $composer = json_decode(file_get_contents($akar.'/composer.json'), true);
    $paket = array_merge(array_keys($composer['require']), array_keys($composer['require-dev'] ?? []));

    expect($paket)->not->toContain('laravel/horizon')->not->toContain('predis/predis')
        ->and(file_exists($akar.'/config/horizon.php'))->toBeFalse()
        ->and(file_exists($akar.'/app/Providers/HorizonServiceProvider.php'))->toBeFalse()
        ->and(file_get_contents($akar.'/bootstrap/providers.php'))->not->toContain('Horizon')
        ->and(file_get_contents($akar.'/routes/console.php'))->not->toContain('horizon');
});

it('tidak merujuk Horizon atau izin horizon di kode, seeder, dan migrasi', function () {
    $akar = dirname(__DIR__, 2);

    foreach (['app', 'database'] as $folder) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar.'/'.$folder)) as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            expect(file_get_contents($berkas->getPathname()))
                ->not->toContain('Laravel\\Horizon')
                ->not->toMatch('/horizon\.|viewHorizon/i');
        }
    }
});
