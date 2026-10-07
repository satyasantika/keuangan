<?php

test('App\Support\Uang tidak memakai round atau floatval', function () {
    $isi = file_get_contents(dirname(__DIR__, 2).'/app/Support/Uang.php');
    // Abaikan kata "float" pada pesan/komentar: periksa pemanggilan fungsi saja.
    expect(preg_match('/\b(round|floatval|floor|ceil)\s*\(/', $isi))->toBe(0);
});

test('tidak ada tipe float pada properti atau parameter uang di app', function () {
    $pelanggaran = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $berkas) {
        // Uang sengaja menerima float hanya untuk menolaknya.
        if ($berkas->getExtension() !== 'php' || $berkas->getFilename() === 'Uang.php') {
            continue;
        }

        $isi = file_get_contents($berkas->getPathname());

        if (preg_match('/\bfloat\s+\$?(jumlah|harga|total|nilai|pagu)\w*/i', $isi, $m)) {
            $pelanggaran[] = $berkas->getFilename().': '.$m[0];
        }
    }

    expect($pelanggaran)->toBe([]);
});
