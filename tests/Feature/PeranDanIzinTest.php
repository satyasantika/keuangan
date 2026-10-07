<?php

use App\Enums\Peran;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PenggunaAwalSeeder;
use Database\Seeders\PeranDanIzinSeeder;

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
});

test('seeder menghasilkan tujuh peran sesuai enum', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(Peran::cases())->map->value->sort()->values()->all())
        ->and(Role::count())->toBe(7);
});

test('admin-unit punya pengajuan.buat tetapi tidak punya persetujuan.setujui', function () {
    $user = User::factory()->create()->assignRole(Peran::AdminUnit->value);

    expect($user->can('pengajuan.buat'))->toBeTrue()
        ->and($user->can('persetujuan.setujui'))->toBeFalse();
});

test('pimpinan tidak punya izin buat, ubah, atau hapus apa pun', function () {
    $izin = Role::findByName(Peran::Pimpinan->value, 'web')->permissions->pluck('name');

    expect($izin)->not->toBeEmpty()
        ->and($izin->filter(fn ($n) => preg_match('/\.(buat|ubah|hapus)$/', $n)))->toBeEmpty();
});

test('super-admin tidak punya persetujuan.setujui (pemisahan tugas)', function () {
    $user = User::factory()->create()->assignRole(Peran::SuperAdmin->value);

    expect($user->can('persetujuan.setujui'))->toBeFalse()
        ->and($user->can('pengguna.buat'))->toBeTrue();
});

test('wadek-keuangan dapat menetapkan pagu dan admin-keuangan tidak', function () {
    $wadek = User::factory()->create()->assignRole(Peran::WadekKeuangan->value);
    $admin = User::factory()->create()->assignRole(Peran::AdminKeuangan->value);

    expect($wadek->can('pagu.setujui'))->toBeTrue()
        ->and($admin->can('pagu.setujui'))->toBeFalse()
        ->and($admin->can('pagu.buat'))->toBeTrue();
});

test('seeder dijalankan dua kali tidak menggandakan data', function () {
    $peran = Role::count();
    $izin = Permission::count();
    $pivot = DB::table('role_has_permissions')->count();

    $this->seed(PeranDanIzinSeeder::class);

    expect(Role::count())->toBe($peran)
        ->and(Permission::count())->toBe($izin)
        ->and(DB::table('role_has_permissions')->count())->toBe($pivot);
});

test('peran dan izin memakai uuid sebagai kunci', function () {
    expect(Str::isUuid(Role::first()->getKey()))->toBeTrue()
        ->and(Str::isUuid(Permission::first()->getKey()))->toBeTrue();
});

test('penggunaan awal membuat super-admin dari env dan idempoten', function () {
    config(['keuangan.seed_admin.email' => 'admin@unsil.ac.id', 'keuangan.seed_admin.password' => 'rahasia-kuat-123']);

    $this->seed(PenggunaAwalSeeder::class);
    $this->seed(PenggunaAwalSeeder::class);

    expect(User::where('email', 'admin@unsil.ac.id')->count())->toBe(1)
        ->and(User::where('email', 'admin@unsil.ac.id')->first()->hasRole('super-admin'))->toBeTrue();
});

test('seeder pengguna awal gagal dengan pesan jelas bila env kosong', function () {
    config(['keuangan.seed_admin.email' => null, 'keuangan.seed_admin.password' => null]);

    expect(fn () => $this->seed(PenggunaAwalSeeder::class))
        ->toThrow(RuntimeException::class, 'SEED_ADMIN_EMAIL');
});
