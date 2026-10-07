<?php

namespace Tests\Feature;

use Tests\TestCase;

class KonfigurasiDasarTest extends TestCase
{
    public function test_zona_waktu_aplikasi_adalah_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_locale_aplikasi_adalah_id(): void
    {
        $this->assertSame('id', config('app.locale'));
    }
}
