<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tabel khusus uji (tests/Migrations) ikut dimigrasi bersama migrasi aplikasi.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $app->make('migrator')->path(__DIR__.'/Migrations');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Uji tidak boleh menghubungi Drive/situs luar sungguhan.
        Http::preventStrayRequests();
    }

    /**
     * Di produksi reverse proxy memotong awalan subpath sebelum ke aplikasi, sehingga
     * rute aplikasi tidak berawalan /keuangan. Permintaan uji meniru itu: jalur diambil
     * apa adanya, tidak melalui URL::forceRootUrl() milik APP_URL.
     */
    protected function prepareUrlForRequest($uri)
    {
        $uri = $uri instanceof Uri ? $uri->value() : $uri;

        if (str_starts_with($uri, 'http')) {
            return $uri;
        }

        return 'http://localhost/'.ltrim($uri, '/');
    }
}
