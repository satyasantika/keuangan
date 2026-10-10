<?php

use App\Enums\Peran;
use App\Enums\TahapPersetujuan;
use App\Models\Pengajuan;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\PengajuanDisetujui;
use App\Notifications\PengajuanMenungguTindakan;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    buatTahun(2027);
    config(['services.whatsapp' => ['aktif' => true, 'url' => 'https://wa.gateway.test/send', 'token' => 'TOKEN-RAHASIA-123', 'batas_per_menit' => 20]]);
    $this->pengguna = penggunaDenganPeran(Peran::KepalaUnit, ['no_hp' => '0812-3456-7890']);
    $this->pengajuan = Pengajuan::factory()->diajukan()->create();
    $this->notifikasi = new PengajuanMenungguTindakan($this->pengajuan, TahapPersetujuan::KepalaUnit);
});

test('pesan terkirim ke gateway dengan nomor ternormalisasi dan teks ≤ 300 karakter', function () {
    Http::fake(['wa.gateway.test/*' => Http::response(['status' => true])]);

    expect($this->notifikasi->via($this->pengguna))->toContain(WhatsAppChannel::class);

    app(WhatsAppChannel::class)->send($this->pengguna, $this->notifikasi);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://wa.gateway.test/send'
        && $r['target'] === '6281234567890'
        && mb_strlen($r['message']) <= 300
        && str_contains($r['message'], (string) $this->pengajuan->nomor)
        && $r->header('Authorization') === ['TOKEN-RAHASIA-123']);
});

test('WHATSAPP_AKTIF=false → tidak ada request dan kanal tidak dipakai', function () {
    config(['services.whatsapp.aktif' => false]);
    Http::fake();

    expect($this->notifikasi->via($this->pengguna))->not->toContain(WhatsAppChannel::class);
    app(WhatsAppChannel::class)->send($this->pengguna, $this->notifikasi);

    Http::assertNothingSent();
});

test('notifikasi tidak mendesak tidak memakai WhatsApp; pengguna tanpa no_hp dilewati', function () {
    expect((new PengajuanDisetujui($this->pengajuan))->via($this->pengguna))->not->toContain(WhatsAppChannel::class);

    $tanpaHp = penggunaDenganPeran(Peran::KepalaUnit, ['no_hp' => null]);
    expect($this->notifikasi->via($tanpaHp))->not->toContain(WhatsAppChannel::class);
});

test('respons 500 → exception (job di-retry) dan token tidak tercatat di log', function () {
    Http::fake(['wa.gateway.test/*' => Http::response('galat', 500)]);
    Log::spy();

    expect(fn () => app(WhatsAppChannel::class)->send($this->pengguna, $this->notifikasi))->toThrow(RequestException::class);

    Log::shouldNotHaveReceived('error', fn ($pesan) => str_contains((string) $pesan, 'TOKEN-RAHASIA-123'));
    Log::shouldNotHaveReceived('info', fn ($pesan) => str_contains((string) $pesan, 'TOKEN-RAHASIA-123'));
    expect($this->notifikasi->tries)->toBe(3)->and($this->notifikasi->backoff)->toBe([60, 300, 900]);
});

test('normalisasi nomor', function (string $masuk, ?string $keluar) {
    expect(WhatsAppChannel::normalisasiNomor($masuk))->toBe($keluar);
})->with([
    ['081234567890', '6281234567890'],
    ['+62 812-3456-7890', '6281234567890'],
    ['81234567890', '6281234567890'],
    ['12345', null],
]);
