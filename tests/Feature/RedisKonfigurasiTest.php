<?php

test('koneksi antrean redis memakai koneksi queue', function () {
    expect(config('queue.connections.redis.connection'))->toBe('queue')
        ->and(config('queue.connections.redis.retry_after'))->toBe(660);
});

test('cache redis memakai koneksi cache dan lock default', function () {
    expect(config('cache.stores.redis.connection'))->toBe('cache')
        ->and(config('cache.stores.redis.lock_connection'))->toBe('default');
});

test('indeks db redis dibaca dari env', function () {
    expect((string) config('database.redis.cache.database'))->toBe((string) env('REDIS_CACHE_DB', '1'))
        ->and((string) config('database.redis.queue.database'))->toBe((string) env('REDIS_QUEUE_DB', '2'))
        ->and((string) config('database.redis.default.database'))->toBe((string) env('REDIS_DB', '0'));
});

test('horizon memiliki dua supervisor dengan antrean yang ditentukan', function () {
    $default = config('horizon.defaults');

    expect($default['supervisor-umum']['queue'])->toBe(['notifikasi', 'default'])
        ->and($default['supervisor-berat']['queue'])->toBe(['ekspor', 'impor'])
        ->and($default['supervisor-berat']['timeout'])->toBe(600)
        ->and($default['supervisor-berat']['memory'])->toBe(512)
        ->and(config('horizon.waits')['redis:notifikasi'])->toBe(60);
});
