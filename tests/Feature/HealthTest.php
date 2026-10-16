<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->freezeTime();
    config(['app.version' => '9.9.9']);
    cache()->put(config('app.kode').':detak-penjadwal', now()->getTimestamp(), 600);
});

test('health mengembalikan 200 dengan struktur json lengkap tanpa redis', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'db' => 'ok',
            'job_tertunda' => 0,
            'job_gagal_24j' => 0,
            'detak_penjadwal' => 'ok',
            'versi' => '9.9.9',
            'waktu' => now()->toIso8601String(),
        ]);
});

test('health menghitung job tertunda lebih dari 10 menit tanpa mengubah status', function () {
    DB::table('jobs')->insert([
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(11)->getTimestamp(), 'created_at' => now()->subMinutes(11)->getTimestamp()],
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(2)->getTimestamp(), 'created_at' => now()->subMinutes(2)->getTimestamp()],
    ]);

    $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('job_tertunda', 1);
});

test('health menghitung job gagal dalam 24 jam terakhir', function () {
    $gagal = fn (string $kapan) => [
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'x', 'failed_at' => $kapan,
    ];

    DB::table('failed_jobs')->insert([$gagal(now()->subHours(2)->toDateTimeString()), $gagal(now()->subDays(3)->toDateTimeString())]);

    $this->getJson('/api/health')->assertOk()->assertJsonPath('job_gagal_24j', 1);
});

test('health menandai detak penjadwal terlambat, ok, atau belum ada tanpa menurunkan status', function () {
    cache()->put(config('app.kode').':detak-penjadwal', now()->subMinutes(6)->getTimestamp(), 600);
    $this->getJson('/api/health')->assertOk()->assertJsonPath('detak_penjadwal', 'terlambat');

    cache()->forget(config('app.kode').':detak-penjadwal');
    $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('detak_penjadwal', 'belum-ada');
});

test('health mengembalikan 503 hanya bila db gagal, tanpa membocorkan pesan', function () {
    DB::shouldReceive('select')->andThrow(new RuntimeException('rahasia: db mati di 10.0.0.5'));
    DB::shouldReceive('table')->andThrow(new RuntimeException('rahasia: db mati di 10.0.0.5'));

    $respons = $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJson(['status' => 'gagal', 'db' => 'gagal', 'job_tertunda' => 'gagal', 'job_gagal_24j' => 'gagal']);

    expect($respons->getContent())->not->toContain('rahasia')->not->toContain('10.0.0.5');
});

test('health tidak memuat kunci redis', function () {
    $this->getJson('/api/health')->assertJsonMissingPath('redis');
});

test('health tidak memerlukan login', function () {
    $this->assertGuest();

    $this->getJson('/api/health')->assertOk();
});

test('route health bernama health dan berawalan subpath', function () {
    expect(route('health', absolute: false))->toBe('/api/health');
});
