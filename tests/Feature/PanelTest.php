<?php

use App\Models\User;

test('pengguna aktif dapat membuka panel setelah login', function () {
    $this->actingAs(User::factory()->create())
        ->get('/panel')
        ->assertOk();
});

test('pengguna nonaktif ditolak dengan 403', function () {
    $this->actingAs(User::factory()->nonaktif()->create())
        ->get('/panel')
        ->assertForbidden();
});

test('tamu diarahkan ke halaman login panel', function () {
    $this->get('/panel')->assertRedirect(route('filament.keuangan.auth.login'));
});

test('halaman login tampil dalam bahasa indonesia dengan versi di footer', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('Keuangan FKIP Unsil v'.config('app.version'));
});

test('footer panel menampilkan versi aplikasi', function () {
    $this->actingAs(User::factory()->create())
        ->get('/panel')
        ->assertSee('Keuangan FKIP Unsil v'.config('app.version'));
});

test('halaman utama diarahkan ke panel', function () {
    $this->get('/')->assertRedirect('/panel');
});

test('kolom pengguna tambahan tersedia', function () {
    $user = User::factory()->create(['nip' => '198001012005011001', 'no_hp' => '081234567890']);

    expect($user->fresh())
        ->nip->toBe('198001012005011001')
        ->aktif->toBeTrue();
});
