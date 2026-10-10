<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $url = 'https://supportfkip.unsil.ac.id/keuangan';

    config([
        'app.url' => $url,
        'app.asset_url' => $url,
        'session.path' => '/keuangan',
        'session.cookie' => 'keuangan_session',
    ]);

    URL::forceRootUrl($url);
    URL::forceScheme('https');
    URL::useAssetOrigin($url);
});

afterEach(function () {
    URL::forceRootUrl(null);
    URL::useAssetOrigin(null);
});

test('url dan asset berawalan subpath produksi', function () {
    expect(url('/'))->toStartWith('https://supportfkip.unsil.ac.id/keuangan')
        ->and(url('/panel'))->toBe('https://supportfkip.unsil.ac.id/keuangan/panel')
        ->and(asset('build/app.js'))->toBe('https://supportfkip.unsil.ac.id/keuangan/build/app.js');
});

test('halaman login panel memasang cookie sesi keuangan_session ber-path /keuangan', function () {
    $respons = $this->get('/panel/login')->assertOk();

    $cookie = collect($respons->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'keuangan_session');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getPath())->toBe('/keuangan');
});

test('redirect tamu ke login tetap di bawah subpath', function () {
    Route::middleware(['web', 'auth'])->get('/_uji/terlindung', fn () => 'rahasia');
    app('router')->getRoutes()->refreshNameLookups();

    // Tamu diarahkan ke halaman login panel (bootstrap/app.php redirectGuestsTo).
    $this->get('/_uji/terlindung')
        ->assertRedirect('https://supportfkip.unsil.ac.id/keuangan/panel/login');
});

test('URL update Livewire tidak menggandakan awalan subpath (dengan maupun tanpa X-Forwarded-Prefix)', function (array $header) {
    $html = $this->withHeaders($header)->get('/panel/login')->assertOk()->getContent();
    preg_match('/data-update-uri="([^"]+)"/', $html, $cocok);

    expect($cocok[1])->toMatch('#^https://supportfkip\.unsil\.ac\.id/keuangan/livewire-[a-z0-9]+/update$#');
})->with([
    'tanpa header' => [[]],
    'dengan X-Forwarded-Prefix' => [['X-Forwarded-Prefix' => '/keuangan', 'X-Forwarded-Proto' => 'https']],
]);
