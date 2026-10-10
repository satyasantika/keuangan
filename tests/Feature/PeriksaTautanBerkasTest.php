<?php

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Enums\StatusCekTautan;
use App\Filament\Infolists\DaftarTautanBerkas;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Akun;
use App\Models\TautanBerkas;
use App\Models\Unit;
use App\Notifications\TautanBerkasBermasalah;
use Database\Seeders\UnitContohSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(UnitContohSeeder::class);
    $this->tahun = buatTahun(2027);
    $this->pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $this->adminPmat = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]);
    $this->pengajuan = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($this->tahun->id), [itemRab(Akun::factory()->create()->id)], $this->adminPmat);
});

function simpanTautan(object $test, JenisTautan $jenis, string $id): TautanBerkas
{
    return app(SimpanTautanBerkas::class)->handle($test->pengajuan, $jenis, "https://drive.google.com/file/d/{$id}/view", 'Bukti '.$id, true, $test->adminPmat);
}

test('(a) 200 anonim pada kuitansi → peringatan_privasi + notifikasi', function () {
    Notification::fake();
    Http::fake(['drive.google.com/*' => Http::response('', 200)]);

    $tautan = simpanTautan($this, JenisTautan::Kuitansi, '1Publik');

    expect($tautan->fresh()->status_cek)->toBe(StatusCekTautan::DapatDiakses)
        ->and($tautan->fresh()->peringatan_privasi)->toBeTrue()
        ->and($tautan->fresh()->dicek_pada)->not->toBeNull();

    Notification::assertSentTo($this->adminPmat, TautanBerkasBermasalah::class, fn ($n) => $n->alasan === TautanBerkasBermasalah::AKSES_PUBLIK);
});

test('(a2) 200 anonim pada TOR tidak menandai privasi', function () {
    Notification::fake();
    Http::fake(['drive.google.com/*' => Http::response('', 200)]);

    $tautan = simpanTautan($this, JenisTautan::Tor, '1Tor');

    expect($tautan->fresh()->peringatan_privasi)->toBeFalse();
    Notification::assertNothingSent();
});

test('(b) redirect ke accounts.google.com → dapat diakses tanpa peringatan', function () {
    Notification::fake();
    Http::fake([
        'drive.google.com/*' => Http::response('', 302, ['Location' => 'https://accounts.google.com/ServiceLogin?continue=x']),
    ]);

    $tautan = simpanTautan($this, JenisTautan::Kuitansi, '1Terbatas');

    expect($tautan->fresh()->status_cek)->toBe(StatusCekTautan::DapatDiakses)
        ->and($tautan->fresh()->peringatan_privasi)->toBeFalse();
    Notification::assertNothingSent();
});

test('(c) 404 → tidak dapat diakses + notifikasi', function () {
    Notification::fake();
    Http::fake(['drive.google.com/*' => Http::response('', 404)]);

    $tautan = simpanTautan($this, JenisTautan::Tor, '1Hilang');

    expect($tautan->fresh()->status_cek)->toBe(StatusCekTautan::TidakDapatDiakses);
    Notification::assertSentTo($this->adminPmat, TautanBerkasBermasalah::class, fn ($n) => $n->alasan === TautanBerkasBermasalah::TIDAK_DAPAT_DIAKSES);
});

test('(d) ConnectionException → tidak dapat diakses, simpan tetap berhasil', function () {
    Notification::fake();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $tautan = simpanTautan($this, JenisTautan::Nota, '1Timeout');

    expect(TautanBerkas::whereKey($tautan->id)->exists())->toBeTrue()
        ->and($tautan->fresh()->status_cek)->toBe(StatusCekTautan::TidakDapatDiakses);
});

test('(e) permintaan memakai HEAD atau Range bytes=0-0, tanpa Cookie/Authorization', function () {
    Http::fake([
        'drive.google.com/*' => Http::sequence()->push('', 405)->push('', 206),
    ]);

    simpanTautan($this, JenisTautan::Tor, '1Head');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => $r->method() === 'HEAD' && ! $r->hasHeader('Cookie') && ! $r->hasHeader('Authorization'));
    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->header('Range') === ['bytes=0-0'] && ! $r->hasHeader('Cookie') && ! $r->hasHeader('Authorization'));
});

test('(f) urlPratinjau mengikuti pola Drive', function () {
    Http::fake(['*' => Http::response('', 403)]);
    $penyimpanan = app(PenyimpananBerkas::class);

    $pdf = simpanTautan($this, JenisTautan::Tor, '1Pdf');
    $foto = simpanTautan($this, JenisTautan::FotoKegiatan, '1Foto');
    $unsil = app(SimpanTautanBerkas::class)->handle($this->pengajuan, JenisTautan::Lainnya, 'https://lppm.unsil.ac.id/x.pdf', 'Lain', false, $this->adminPmat);

    expect($penyimpanan->urlPratinjau($pdf))->toBe('https://drive.google.com/file/d/1Pdf/preview')
        ->and($penyimpanan->urlPratinjau($foto))->toBe('https://lh3.googleusercontent.com/d/1Foto')
        ->and($penyimpanan->urlPratinjau($unsil))->toBeNull();
});

test('(g) halaman pengajuan menampilkan daftar tautan hanya untuk yang berhak', function () {
    Http::fake(['*' => Http::response('', 200)]);
    simpanTautan($this, JenisTautan::Kuitansi, '1Tampil');

    $this->actingAs($this->adminPmat)->get("/panel/pengajuan/{$this->pengajuan->id}")
        ->assertOk()
        ->assertSee('Bukti 1Tampil')
        ->assertSee('Akses publik — minta dibatasi')
        ->assertSee('https://drive.google.com/file/d/1Tampil/preview', false)
        ->assertSee('rel="noopener noreferrer"', false);

    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => Unit::where('kode', 'PBIO')->value('id')]);
    $this->actingAs($adminPbio);
    expect(DaftarTautanBerkas::butir($this->pengajuan))->toBe([]);
    $this->get("/panel/pengajuan/{$this->pengajuan->id}")->assertForbidden();
});

test('job memakai antrean default dan rate limit periksa-tautan', function () {
    $job = new PeriksaTautanBerkas(new TautanBerkas);

    expect($job->queue)->toBe('default')
        ->and($job->tries)->toBe(3)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});
