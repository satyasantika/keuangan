<?php

use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    config([
        'services.google.aktif' => true,
        'services.google.client_id' => 'id-uji',
        'services.google.client_secret' => 'rahasia',
    ]);
});

function googleSebagai(string $email, array $tambahan = []): void
{
    Socialite::fake('google', GoogleUser::fake(array_merge([
        'id' => 'g-'.md5($email), 'name' => 'Nama Google', 'email' => $email, 'email_verified' => true,
    ], $tambahan)));
}

function tujuanPanel(): string
{
    return Filament::getPanel('keuangan')->getUrl();
}

test('fitur nonaktif: kedua rute 404 dan halaman masuk tidak memuat tombol', function () {
    config(['services.google.aktif' => false]);

    $this->get(route('auth.google.redirect'))->assertNotFound();
    $this->get(route('auth.google.callback'))->assertNotFound();
    $this->get(route('filament.keuangan.auth.login'))->assertOk()->assertDontSee('Masuk dengan Google');
});

test('halaman masuk memuat tombol dan tautannya', function () {
    $this->get(route('filament.keuangan.auth.login'))
        ->assertOk()
        ->assertSee('Masuk dengan Google')
        ->assertSee(route('auth.google.redirect', ['asal' => route('filament.keuangan.auth.login')]), false);
});

test('mengarahkan ke Google dengan redirect_uri bawaan', function () {
    $lokasi = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');

    expect($lokasi)->toStartWith('https://accounts.google.com/');

    parse_str((string) parse_url($lokasi, PHP_URL_QUERY), $q);

    expect($q['redirect_uri'])->toBe(route('auth.google.callback'))
        ->and(urldecode($q['scope']))->toContain('openid');

    $subpath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

    if ($subpath !== '') {
        expect($q['redirect_uri'])->toContain($subpath.'/auth/google/callback');
    }
});

test('memasukkan akun yang sudah ada dan menautkan google_id', function () {
    $user = User::factory()->create(['email' => 'budi@unsil.ac.id']);
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertRedirect(tujuanPanel());

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->google_id)->toBe('g-'.md5('budi@unsil.ac.id'));
});

test('mencocokkan surel tanpa peduli huruf', function () {
    $user = User::factory()->create(['email' => 'budi@unsil.ac.id']);
    googleSebagai('Budi@Unsil.ac.id');

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

test('membuat akun baru untuk surel yang belum terdaftar', function () {
    googleSebagai('baru@gmail.com');

    $this->get(route('auth.google.callback'))->assertRedirect(tujuanPanel());

    $this->assertAuthenticated();
    $u = User::firstWhere('email', 'baru@gmail.com');

    expect($u)->not->toBeNull()
        ->and($u->name)->toBe('Nama Google')
        ->and($u->aktif)->toBeTrue()
        ->and($u->email_verified_at)->not->toBeNull()
        ->and($u->google_id)->toBe('g-'.md5('baru@gmail.com'));
});

test('menolak surel yang belum diverifikasi Google', function () {
    googleSebagai('x@gmail.com', ['email_verified' => false]);

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Surel akun Google Anda belum terverifikasi.');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('menolak akun yang sudah tertaut ke Google lain', function () {
    User::factory()->create(['email' => 'budi@unsil.ac.id'])->forceFill(['google_id' => 'google-lain'])->save();
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Akun ini sudah ditautkan ke akun Google lain.');

    $this->assertGuest();
});

test('menolak akun nonaktif', function () {
    User::factory()->nonaktif()->create(['email' => 'budi@unsil.ac.id']);
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Akun Anda dinonaktifkan. Hubungi admin.');

    $this->assertGuest();
});

test('menolak akun ber-MFA', function () {
    $user = User::factory()->create(['email' => 'budi@unsil.ac.id']);
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Akun ini memakai verifikasi dua langkah; masuk dengan surel dan kata sandi.');

    $this->assertGuest();
});

test('membatalkan di Google kembali ke halaman masuk', function () {
    $this->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.keuangan.auth.login'))
        ->assertSessionHas('galat_google', 'Masuk dengan Google dibatalkan.');

    $this->assertGuest();
});

test('state OAuth tidak cocok kembali ke halaman masuk', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('filament.keuangan.auth.login'))
        ->assertSessionHas('galat_google', 'Sesi masuk Google kedaluwarsa. Silakan coba lagi.');

    $this->assertGuest();
});

test('kembali ke asal bila asal berada di dalam aplikasi, mengabaikan asal di luar aplikasi', function () {
    $this->withSession(['google_asal' => url('/halaman-x')])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(url('/halaman-x'));

    $this->withSession(['google_asal' => rtrim(url('/'), '/').'.evil.example/'])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.keuangan.auth.login'));

    $this->withSession(['google_asal' => 'https://evil.example/'])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.keuangan.auth.login'));
});

test('pengguna yang sudah masuk langsung diarahkan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('auth.google.redirect'))->assertRedirect(tujuanPanel());
});

test('membatasi 10 permintaan per menit per IP', function () {
    googleSebagai('x@gmail.com', ['email_verified' => false]);

    foreach (range(1, 10) as $i) {
        $this->get(route('auth.google.callback'))->assertRedirect();
    }

    $this->get(route('auth.google.callback'))->assertStatus(429);
});

test('asal bukan string tidak menyebabkan galat 500', function () {
    $lokasi = $this->get(route('auth.google.redirect', ['asal' => ['x']]))->assertRedirect()->headers->get('Location');

    expect($lokasi)->toStartWith('https://accounts.google.com/');

    $this->withSession(['google_asal' => ['x']])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.keuangan.auth.login'));
});
