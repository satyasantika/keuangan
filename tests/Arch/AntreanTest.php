<?php

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): hanya dua antrean, `default` dan `berat`.
 */
function berkasPhp(string $folder): Generator
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder)) as $berkas) {
        if ($berkas->getExtension() === 'php') {
            yield $berkas->getPathname() => file_get_contents($berkas->getPathname());
        }
    }
}

it('hanya memakai antrean default dan berat', function () {
    $akar = dirname(__DIR__, 2);
    $diizinkan = ['default', 'berat'];

    foreach (berkasPhp($akar.'/app') as $jalur => $isi) {
        preg_match_all('/(?:onQueue\(|\$queue\s*=|getJobQueue\(\)[^{]*\{\s*return)\s*[\'"]([^\'"]+)[\'"]/', $isi, $cocok);
        preg_match_all('/viaQueues\(\)[^{]*\{\s*return\s*\[([^\]]*)\]/', $isi, $via);

        foreach ($via[1] as $daftar) {
            preg_match_all('/=>\s*[\'"]([^\'"]+)[\'"]/', $daftar, $nama);
            $cocok[1] = [...$cocok[1], ...$nama[1]];
        }

        foreach ($cocok[1] as $nama) {
            expect($nama)->toBeIn($diizinkan, str_replace($akar.'/', '', $jalur)." memakai antrean '{$nama}'");
        }
    }
});

it('tidak memakai Redis di kode aplikasi', function () {
    $akar = dirname(__DIR__, 2);
    foreach (berkasPhp($akar.'/app') as $isi) {
        expect($isi)->not->toContain('Redis::')->not->toContain('Illuminate\\Support\\Facades\\Redis');
    }
});
