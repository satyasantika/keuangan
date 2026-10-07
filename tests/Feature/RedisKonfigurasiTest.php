<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedisKonfigurasiTest extends TestCase
{
    public function test_koneksi_antrean_redis_memakai_koneksi_queue(): void
    {
        $this->assertSame('queue', config('queue.connections.redis.connection'));
        $this->assertSame(660, config('queue.connections.redis.retry_after'));
    }

    public function test_cache_redis_memakai_koneksi_cache_dan_lock_default(): void
    {
        $this->assertSame('cache', config('cache.stores.redis.connection'));
        $this->assertSame('default', config('cache.stores.redis.lock_connection'));
    }

    public function test_indeks_db_redis_dibaca_dari_env(): void
    {
        $this->assertSame((string) env('REDIS_CACHE_DB', '1'), (string) config('database.redis.cache.database'));
        $this->assertSame((string) env('REDIS_QUEUE_DB', '2'), (string) config('database.redis.queue.database'));
        $this->assertSame((string) env('REDIS_DB', '0'), (string) config('database.redis.default.database'));
    }

    public function test_horizon_memiliki_dua_supervisor_dan_antrean_yang_ditentukan(): void
    {
        $default = config('horizon.defaults');

        $this->assertSame(['notifikasi', 'default'], $default['supervisor-umum']['queue']);
        $this->assertSame(['ekspor', 'impor'], $default['supervisor-berat']['queue']);
        $this->assertSame(600, $default['supervisor-berat']['timeout']);
        $this->assertSame(512, $default['supervisor-berat']['memory']);
        $this->assertSame(60, config('horizon.waits')['redis:notifikasi']);
    }
}
