<?php

use App\Enums\Peran;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/uji-galat/{kode}', fn (int $kode) => abort($kode))->whereNumber('kode');
});

test('landing page memuat fitur, alur, panduan per peran tanpa kartu super-admin, dan tautan masuk', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Persetujuan berjenjang')
        ->assertSee('Alur kerja')
        ->assertSee(route('filament.keuangan.auth.login'), false)
        ->assertSee(asset('css/keuangan.css'), false)
        ->assertDontSee('panduan/super-admin.html', false)
        ->assertDontSee('Admin TI');

    foreach ([Peran::AdminUnit, Peran::KepalaUnit, Peran::AdminKeuangan, Peran::Bpp, Peran::WadekKeuangan, Peran::Pimpinan] as $peran) {
        $this->get('/')->assertSee(asset("panduan/{$peran->value}.html"), false);
    }
});

test('indeks panduan HTML tidak menampilkan kartu super-admin dan semua halaman peran lain ada', function () {
    $indeks = (string) file_get_contents(public_path('panduan/index.html'));

    expect($indeks)->not->toContain('super-admin.html');

    foreach (['admin-unit', 'kepala-unit', 'admin-keuangan', 'bpp', 'wadek-keuangan', 'pimpinan'] as $slug) {
        expect($indeks)->toContain("{$slug}.html")->and(file_exists(public_path("panduan/{$slug}.html")))->toBeTrue();
    }
});

test('halaman masuk memakai tata letak kiri-kanan bertema sama dengan landing', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('k-pisah__kiri', false)
        ->assertSee('k-pisah__kanan', false)
        ->assertSee(asset('css/keuangan.css'), false);
});

test('setiap kode eror HTTP yang didukung tampil bertema dengan teks Indonesia', function (int $kode) {
    $teks = trans("galat.{$kode}");

    expect($teks)->toBeArray();

    $respons = $this->get("/uji-galat/{$kode}");

    $respons->assertStatus($kode)
        ->assertSee((string) $kode)
        ->assertSee($teks['judul'], false)
        ->assertSee('k-galat__kotak', false)
        ->assertSee('Ke beranda');

    if ($kode >= 500) {
        $respons->assertSee('Waktu kejadian');
    }
})->with([400, 401, 403, 404, 405, 408, 409, 410, 413, 414, 415, 419, 422, 423, 429, 500, 501, 502, 503, 504]);

test('kode eror tak terdaftar memakai cadangan 4xx/5xx yang bertema', function (int $kode) {
    $this->get("/uji-galat/{$kode}")->assertStatus($kode)->assertSee('k-galat__kotak', false)->assertSee('Terjadi kesalahan');
})->with([418, 507]);

test('tombol coba lagi hanya untuk eror yang wajar diulang dan metode GET', function () {
    $this->get('/uji-galat/503')->assertSee('Coba lagi');
    $this->get('/uji-galat/404')->assertDontSee('Coba lagi');
});

test('halaman eror statis nginx tersedia dan terhubung di konfigurasi', function () {
    $nginx = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    expect($nginx)->toContain('error_page 502 503 504 /galat/layanan.html')
        ->toContain('error_page 413 /galat/terlalu-besar.html')
        ->and((string) file_get_contents(public_path('galat/layanan.html')))->toContain('503')->toContain('k-galat__kotak')
        ->and((string) file_get_contents(public_path('galat/terlalu-besar.html')))->toContain('413');
});
