<?php

use App\Enums\Peran;
use Database\Seeders\PanduanSeeder;

/**
 * Panduan HTML per peran di public/panduan.
 */
function folderPanduan(): string
{
    return public_path('panduan');
}

test('setiap peran memiliki halaman panduan dan tercantum di index.html', function () {
    $folder = folderPanduan();
    $index = (string) file_get_contents($folder.'/index.html');

    foreach (Peran::cases() as $peran) {
        expect(is_file("{$folder}/{$peran->value}.html"))->toBeTrue("{$peran->value}.html tidak ada");

        // Kartu super-admin sengaja tidak ditampilkan di indeks/landing; halamannya dibuka dari menu pengguna.
        if ($peran !== Peran::SuperAdmin) {
            expect($index)->toContain("href=\"{$peran->value}.html\"");
        }
    }
});

test('setiap gambar ada, ber-alt, dan semua href/src relatif', function () {
    $folder = folderPanduan();
    foreach (glob($folder.'/*.html') as $berkas) {
        $html = (string) file_get_contents($berkas);
        preg_match_all('/<img\b[^>]*>/i', $html, $img);

        foreach ($img[0] as $tag) {
            preg_match('/src="([^"]+)"/', $tag, $src);
            expect(is_file($folder.'/'.$src[1]))->toBeTrue(basename($berkas).": {$src[1]} tidak ada")
                ->and($tag)->toMatch('/alt="[^"]{10,}"/');
        }

        preg_match_all('/(?:href|src)="([^"]*)"/i', $html, $url);

        foreach ($url[1] as $u) {
            expect($u)->not->toStartWith('/')
                ->not->toStartWith('http://localhost');
        }
    }
});

test('halaman panduan mandiri: tanpa CDN atau skrip eksternal, siap cetak, berbahasa Indonesia', function () {
    $folder = folderPanduan();
    foreach (glob($folder.'/*.html') as $berkas) {
        $html = (string) file_get_contents($berkas);

        expect($html)->toContain('<html lang="id">')
            ->toContain('@media print')
            ->toContain('name="viewport"')
            ->not->toMatch('/<(script|link)\b[^>]*(src|href)="https?:/i');
    }
});

test('ukuran total public/panduan ≤ 15 MB', function () {
    $folder = folderPanduan();
    $total = 0;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $berkas) {
        $total += $berkas->getSize();
    }

    expect($total)->toBeLessThanOrEqual(15 * 1024 * 1024);
});

test('landing page (halaman masuk) memuat tautan panduan dan menu panel mengarah ke panduan peran', function () {
    $this->get('/')->assertOk()->assertSee(asset('panduan/bpp.html'), false);
    $this->get('/panel/login')->assertOk()->assertSee(asset('panduan/index.html'), false)->assertSee('Panduan Pengguna');

    $this->actingAs(penggunaDenganPeran(Peran::Bpp))->get(route('panduan.pengguna'))->assertRedirect(asset('panduan/bpp.html'));
});

test('PanduanSeeder menolak APP_ENV selain local', function (string $lingkungan) {
    $this->app->detectEnvironment(fn () => $lingkungan);
    config(['keuangan.panduan.password' => 'SandiPanduan123']);

    expect(fn () => app(PanduanSeeder::class)->run())->toThrow(RuntimeException::class, 'local');
})->with(['production', 'staging', 'testing']);

test('akun demo panduan bebas MFA hanya di local', function () {
    $akun = penggunaDenganPeran(Peran::AdminKeuangan, ['email' => 'panduan.admin-keuangan@contoh.test'], mfa: false);

    expect($akun->wajibMfa())->toBeTrue();

    $this->app->detectEnvironment(fn () => 'local');
    expect($akun->wajibMfa())->toBeFalse()
        ->and(penggunaDenganPeran(Peran::AdminKeuangan, mfa: false)->wajibMfa())->toBeTrue();
});
