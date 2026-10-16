<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;

arch('semua model memakai HasUuids')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(HasUuids::class);

test('migrasi aplikasi tidak memakai kunci auto-increment', function () {
    // Tabel infrastruktur kerangka kerja yang diizinkan.
    $infrastruktur = ['create_cache_table', 'create_jobs_table'];

    $dilarang = [
        '/->id\(/',
        '/foreignId\(/',
        '/(?<!uuid)(?<!Uuid)(?<![A-Za-z])morphs\(/',
        '/bigIncrements\(/',
        '/(?<![A-Za-z])increments\(/',
    ];

    $pelanggaran = [];

    foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $berkas) {
        if (collect($infrastruktur)->contains(fn ($nama) => str_contains($berkas, $nama))) {
            continue;
        }

        $isi = file_get_contents($berkas);

        foreach ($dilarang as $pola) {
            if (preg_match($pola, $isi)) {
                $pelanggaran[] = basename($berkas).' memuat '.$pola;
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});

test('migrasi tidak memakai char/binary eksplisit untuk kolom id', function () {
    // kolom id/FK/morph wajib lewat helper Laravel (uuid, foreignUuid, uuidMorphs) agar tipenya sama
    $dilarang = [
        '/->char\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*36/' => "char('..._id', 36)",
        '/->binary\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*16/' => "binary('..._id', 16)",
    ];

    $pelanggaran = [];

    foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $berkas) {
        $isi = file_get_contents($berkas);

        foreach ($dilarang as $pola => $nama) {
            if (preg_match($pola, $isi)) {
                $pelanggaran[] = basename($berkas)." memuat {$nama}";
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});
