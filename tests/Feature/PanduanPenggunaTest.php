<?php

use App\Enums\Peran;
use App\Models\Pengajuan;
use App\Models\User;
use Database\Seeders\UatSeeder;
use Illuminate\Support\Facades\Hash;

test('UatSeeder menolak berjalan di production dan local', function (string $lingkungan) {
    $this->app->detectEnvironment(fn () => $lingkungan);
    config(['keuangan.uat.password' => 'RahasiaUji123']);

    // Dipanggil langsung: `db:seed` di production meminta konfirmasi interaktif.
    expect(fn () => app(UatSeeder::class)->run())->toThrow(RuntimeException::class, 'staging');
    expect(User::query()->where('email', 'like', '%contoh.test')->exists())->toBeFalse();
})->with(['production', 'local']);

test('UatSeeder di staging membuat akun uji dengan surel penguji dan menolak tanpa kata sandi', function () {
    $this->app->detectEnvironment(fn () => 'staging');
    config(['keuangan.uat.password' => null]);

    expect(fn () => $this->seed(UatSeeder::class))->toThrow(RuntimeException::class, 'UAT_PASSWORD');

    config(['keuangan.uat.password' => 'RahasiaUji123', 'keuangan.uat.emails' => 'admin-keuangan=Ani@Unsil.ac.id, wadek=bukan-surel']);
    $this->seed(UatSeeder::class);

    $ani = User::query()->where('email', 'ani@unsil.ac.id')->firstOrFail();
    expect($ani->hasRole(Peran::AdminKeuangan->value))->toBeTrue()
        ->and(Hash::check('RahasiaUji123', $ani->password))->toBeTrue()
        ->and(User::query()->where('email', 'uat.wadek@contoh.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'like', '%@demo.test')->exists())->toBeFalse()
        ->and(Pengajuan::query()->count())->toBeGreaterThan(5);
})->group('lambat');
