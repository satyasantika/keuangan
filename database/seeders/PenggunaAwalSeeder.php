<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class PenggunaAwalSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('keuangan.seed_admin.email');
        $password = config('keuangan.seed_admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('SEED_ADMIN_EMAIL dan SEED_ADMIN_PASSWORD wajib diisi di .env sebelum menjalankan seeder pengguna awal.');
        }

        $pengguna = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('keuangan.seed_admin.nama'),
                'password' => $password,
                'aktif' => true,
            ],
        );

        $pengguna->forceFill(['email_verified_at' => $pengguna->email_verified_at ?? now()])->save();
        $pengguna->syncRoles([Peran::SuperAdmin->value]);
    }
}
