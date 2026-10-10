<?php

use App\Enums\Peran;
use App\Filament\Pages\Pengaturan;
use App\Models\Akun;
use App\Models\JenisPajak;
use App\Models\JenisPencairan;
use App\Models\KategoriPenggunaanDana;
use App\Models\TarifPajak;
use App\Models\User;
use App\Support\DaftarPerluVerifikasi;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JenisPajakSeeder;
use Database\Seeders\JenisPencairanSeeder;
use Database\Seeders\TarifPajakContohSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Livewire\Livewire;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Kesiapan rilis 1.0.0 (F12.7).
 */
test('seeder production hanya menjalankan peran, super-admin, pengaturan, jenis pencairan, dan jenis pajak', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['keuangan.seed_admin.email' => 'admin.ti@unsil.ac.id', 'keuangan.seed_admin.password' => 'SandiKuat!2027']);

    $perintah = Mockery::mock(Command::class);
    $perintah->shouldReceive('getOutput')->andReturn(new OutputStyle(new ArrayInput([]), new NullOutput));
    $perintah->shouldReceive('warn')->once()->withArgs(fn (string $pesan): bool => str_contains($pesan, 'Data contoh'));

    app(DatabaseSeeder::class)->setContainer(app())->setCommand($perintah)->run();

    expect(User::query()->where('email', 'admin.ti@unsil.ac.id')->firstOrFail()->hasRole(Peran::SuperAdmin->value))->toBeTrue()
        ->and(JenisPencairan::query()->count())->toBeGreaterThan(0)
        ->and(JenisPajak::query()->count())->toBeGreaterThan(0)
        ->and(TarifPajak::query()->count())->toBe(0)
        ->and(KategoriPenggunaanDana::query()->count())->toBe(0)
        ->and(Akun::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);
});

test('daftar perlu verifikasi menghitung baris ADR 0002 dan master yang belum dikonfirmasi', function () {
    $adr = sys_get_temp_dir().'/adr-'.uniqid().'.md';
    file_put_contents($adr, "| No | Parameter | Nilai | Sumber | Status | Oleh | Tgl |\n|---|---|---|---|---|---|---|\n| 1 | A | x | y | Perlu verifikasi | | |\n| 2 | B | x | y | Terkonfirmasi | Ani | 2026 |\n| 4 | C | x | y | Rekomendasi, perlu verifikasi | | |\n");

    expect(DaftarPerluVerifikasi::parameterAdrTerbuka($adr))->toBe(2)
        ->and(DaftarPerluVerifikasi::parameterAdrTerbuka(base_path('docs/keputusan/0002-parameter-keuangan.md')))->toBeGreaterThan(0);
    @unlink($adr);

    $this->seed([JenisPencairanSeeder::class, JenisPajakSeeder::class, TarifPajakContohSeeder::class]);
    $judul = array_column(DaftarPerluVerifikasi::terbuka(), 'jumlah', 'judul');

    expect($judul['Jenis pencairan'])->toBe(JenisPencairan::where('perlu_verifikasi', true)->count())
        ->and($judul)->toHaveKey('Tarif pajak referensi')
        ->and($judul)->toHaveKey('Parameter ADR 0002');

    JenisPencairan::query()->update(['perlu_verifikasi' => false]);
    expect(array_column(DaftarPerluVerifikasi::terbuka(), 'judul'))->not->toContain('Jenis pencairan');
});

test('lencana perlu verifikasi tampil di Pengaturan untuk admin-keuangan & super-admin, tidak untuk bpp', function () {
    $this->seed(JenisPencairanSeeder::class);

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminKeuangan))->test(Pengaturan::class)->assertSee('Perlu verifikasi')->assertSee('Jenis pencairan: ');
    Livewire::actingAs(penggunaDenganPeran(Peran::SuperAdmin))->test(Pengaturan::class)->assertSee('Parameter ADR 0002: ');
    Livewire::actingAs(penggunaDenganPeran(Peran::Bpp))->test(Pengaturan::class)->assertDontSee('Parameter ADR 0002: ');
});

test('versi rilis 1.0.0 tercatat di CHANGELOG dan .env.example', function () {
    expect((string) file_get_contents(base_path('CHANGELOG.md')))->toMatch('/^## \[1\.0\.0\] - \d{4}-\d{2}-\d{2}$/m')
        ->and((string) file_get_contents(base_path('.env.example')))->toContain('APP_VERSION=1.0.0');
});
