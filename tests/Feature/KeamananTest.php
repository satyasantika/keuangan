<?php

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Actions\Persetujuan\KembalikanPengajuan;
use App\Enums\JenisTautan;
use App\Logging\SamarkanDataSensitif;
use App\Notifications\PengajuanDikembalikan;
use App\Rules\TautanBerkasValid;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Pengerasan keamanan: header, XSS, tautan, log, halaman galat.
 */
const XSS = '<script>alert("xss")</script>';

test('header keamanan & CSP dengan frame-src terbatas ada di setiap respons', function () {
    $respons = $this->get('/panel/login')->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeaderMissing('Strict-Transport-Security');

    $csp = (string) $respons->headers->get('Content-Security-Policy');
    preg_match('/frame-src ([^;]+)/', $csp, $frame);

    expect($frame[1])->toBe('https://drive.google.com https://docs.google.com')
        ->and($csp)->toContain("img-src 'self' data: blob: https://lh3.googleusercontent.com")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'");

    $this->get('/api/health')->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('HSTS hanya di production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('/panel/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('route storage lokal tidak menerima/menyajikan berkas', function () {
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.local.upload'))->toBeFalse();
});

test('payload <script> di deskripsi, catatan, dan label tautan tidak tereksekusi di panel, PDF, surel, dan halaman publik', function () {
    $this->travelTo('2027-06-20 10:00:00');
    $k = siapkanAnggaran();
    $p = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($k->tahun->id, ['judul_kegiatan' => 'Lokakarya '.XSS, 'deskripsi' => XSS]), [itemRab($k->akun->id)], $k->adminPmat);
    app(SimpanTautanBerkas::class)->handle($p, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorXss/view', 'TOR <img src=x onerror=alert(1)>', false, $k->adminPmat);
    $p = app(AjukanPengajuan::class)->handle($p->fresh(), $k->adminPmat);
    $p = app(KembalikanPengajuan::class)->handle($p, $k->kepalaPmat, 'Perbaiki '.XSS);

    $panel = $this->actingAs($k->adminPmat)->get("/panel/pengajuan/{$p->id}")->assertOk()->getContent();
    $pdf = view('pdf.pengajuan', ['pengajuan' => $p->fresh()->load(['unit', 'tahunAnggaran', 'rabAktif.item.akun', 'riwayatStatus.user']), 'namaFakultas' => 'FKIP', 'qr' => '', 'dicetakPada' => now()])->render();
    $surel = (string) (new PengajuanDikembalikan($p, 'Perbaiki '.XSS))->toMail($k->adminPmat)->render();
    $publik = $this->get("/verifikasi/{$p->id}")->getContent();

    foreach (['panel' => $panel, 'pdf' => $pdf, 'surel' => $surel, 'publik' => $publik] as $tempat => $html) {
        expect($html)->not->toContain(XSS, $tempat)
            ->not->toContain('<img src=x onerror', $tempat);
    }

    expect($panel)->toContain('&lt;script&gt;');
});

test('semua tautan keluar ber-target _blank memakai rel noopener noreferrer', function () {
    $this->travelTo('2027-06-20 10:00:00');
    $k = siapkanAnggaran();
    $p = buatPengajuanDraf($k);

    $html = $this->actingAs($k->adminPmat)->get("/panel/pengajuan/{$p->id}")->assertOk()->getContent();
    preg_match_all('/<a\b[^>]*target="_blank"[^>]*>/i', $html, $tautan);

    expect($tautan[0])->not->toBeEmpty();

    foreach ($tautan[0] as $a) {
        expect($a)->toMatch('/rel="[^"]*noopener[^"]*noreferrer|rel="[^"]*noreferrer[^"]*noopener/');
    }
});

test('TautanBerkasValid menolak javascript:, data:, http:, dan domain di luar daftar putih', function (string $url) {
    expect(TautanBerkasValid::sah($url))->toBeFalse();
})->with([
    'javascript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'http://drive.google.com/file/d/1Abc/view',
    'https://drive.google.com.evil.com/file/d/1Abc/view',
    'https://evil.com/file/d/1Abc/view',
    'https://bit.ly/abc',
]);

test('processor log menyamarkan kata sandi, token, dan secret MFA', function () {
    $jalur = storage_path('logs/uji-samaran-'.uniqid().'.log');
    expect(config('logging.channels.single.tap'))->toBe([SamarkanDataSensitif::class])
        ->and(config('logging.channels.daily.tap'))->toBe([SamarkanDataSensitif::class])
        ->and(config('logging.channels.stderr.tap'))->toBe([SamarkanDataSensitif::class]);

    config(['logging.channels.uji_samaran' => [...config('logging.channels.single'), 'path' => $jalur]]);
    $log = Log::channel('uji_samaran');

    $log->info('masuk', ['password' => 'Rahasia123', 'data' => ['app_authentication_secret' => 'JBSWY3DP', 'token_whatsapp' => 'tok-xyz'], 'nama' => 'Budi']);
    $isi = (string) file_get_contents($jalur);
    @unlink($jalur);

    expect($isi)->not->toContain('Rahasia123')->not->toContain('JBSWY3DP')->not->toContain('tok-xyz')
        ->toContain('Budi')->toContain(SamarkanDataSensitif::SAMARAN);
});

test('halaman 500 berbahasa Indonesia tanpa jejak stack maupun nama kelas', function () {
    config(['app.debug' => false]);
    Route::get('/uji-galat-500', fn () => throw new RuntimeException('detail rahasia internal'));

    $html = $this->get('/uji-galat-500')->assertStatus(500)->getContent();

    expect($html)->toContain('Terjadi kesalahan pada server')
        ->not->toContain('RuntimeException')
        ->not->toContain('detail rahasia internal')
        ->not->toContain('vendor/laravel');
});

test('halaman 403 & 404 berbahasa Indonesia', function () {
    $this->get('/tidak-ada-halaman-ini')->assertNotFound()->assertSee('Halaman tidak ditemukan');
});
