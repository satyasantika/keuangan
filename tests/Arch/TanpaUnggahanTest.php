<?php

use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;

/**
 * Sistem tidak menerima unggahan berkas; pengecualian hanya impor Excel/CSV.
 */
arch('aplikasi tidak memakai komponen unggah berkas')
    ->expect('App')
    ->not->toUse([FileUpload::class, UploadedFile::class])
    ->ignoring(['App\Filament\Imports', 'App\Jobs\Impor', 'App\Filament\Resources\RekapHonorEksternals']);

test('aplikasi tidak menulis berkas pengguna lewat Storage::put*', function () {
    $pelanggaran = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));

    foreach ($iterator as $berkas) {
        $jalur = str_replace('\\', '/', $berkas->getPathname());

        if (! str_ends_with($jalur, '.php') || str_contains($jalur, '/app/Filament/Imports/') || preg_match('#/app/Jobs/Impor[^/]*\.php$#', $jalur)) {
            continue;
        }

        if (preg_match('/Storage::(?:disk\([^)]*\)->)?put(?:File|FileAs)?\(|->storeAs?\(|->putFile(?:As)?\(/', (string) file_get_contents($jalur))) {
            $pelanggaran[] = $jalur;
        }
    }

    expect($pelanggaran)->toBe([]);
});
