<?php

use Illuminate\Support\Facades\Redis;

test('health mengembalikan 200 dengan struktur json lengkap', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonStructure(['status', 'db', 'redis', 'versi', 'waktu'])
        ->assertJson([
            'status' => 'ok',
            'db' => true,
            'redis' => true,
            'versi' => config('app.version'),
        ]);
});

test('health mengembalikan 503 bila redis gagal tanpa membocorkan pesan', function () {
    Redis::shouldReceive('connection')->andThrow(new RuntimeException('rahasia: redis mati di 10.0.0.5'));

    $respons = $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJson(['status' => 'gagal', 'db' => true, 'redis' => false]);

    expect($respons->getContent())->not->toContain('rahasia')->not->toContain('10.0.0.5');
});

test('health tidak memerlukan login', function () {
    $this->assertGuest();

    $this->getJson('/api/health')->assertOk();
});

test('route health bernama health dan berawalan subpath', function () {
    expect(route('health', absolute: false))->toBe('/api/health');
});
