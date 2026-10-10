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
            throw new RuntimeException('Surel dan kata sandi super-admin awal tidak boleh kosong (SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD).');
        }

        $pengguna = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('keuangan.seed_admin.nama'),
                'password' => $password,
                'aktif' => true,
            ],
        );

        // MFA tidak diwajibkan secara bawaan (keuangan.mfa_wajib), jadi akun awal langsung dapat dipakai.
        $pengguna->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null]);

        $pengguna->forceFill(['email_verified_at' => $pengguna->email_verified_at ?? now()])->save();
        $pengguna->syncRoles([Peran::SuperAdmin->value]);
    }
}
