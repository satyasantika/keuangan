<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Akun;
use App\Models\TahunAnggaran;
use App\Models\User;
use RuntimeException;

/**
 * Akun demo per peran + data rekaan untuk tangkapan layar panduan. HANYA APP_ENV=local.
 * Akun: panduan.<peran>@contoh.test, kata sandi dari PANDUAN_PASSWORD; MFA tidak diwajibkan untuk akun ini
 * di lingkungan local (lihat User::wajibMfa). Tidak memuat data pribadi asli.
 */
class PanduanSeeder extends DemoSeeder
{
    /**
     * Nama akun DemoSeeder → peran pada alamat surel panduan.
     */
    private const AKUN = [
        'admin-keuangan' => 'admin-keuangan',
        'wadek' => 'wadek-keuangan',
        'bpp' => 'bpp',
        'pimpinan' => 'pimpinan',
        'kepala-pmat' => 'kepala-unit',
        'admin-pmat' => 'admin-unit',
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('PanduanSeeder hanya boleh dijalankan di APP_ENV=local.');
        }

        if (blank(config('keuangan.panduan.password'))) {
            throw new RuntimeException('PANDUAN_PASSWORD wajib diisi di .env lokal.');
        }

        parent::run();

        // Antrean kepala-unit PMAT terisi untuk tangkapan layar verifikasi.
        $adminPmat = User::query()->where('email', $this->surel('admin-pmat'))->firstOrFail();
        $this->pengajuanLampau('Seminar Pembelajaran Matematika', -14, (string) TahunAnggaran::bawaanId(), $adminPmat, Akun::query()->where('aktif', true)->orderBy('kode')->firstOrFail());

        $admin = User::query()->firstOrCreate(
            ['email' => 'panduan.super-admin@'.User::DOMAIN_PANDUAN],
            ['name' => 'Admin TI (Contoh)', 'password' => $this->kataSandi(), 'aktif' => true],
        );
        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()])->save();
        $admin->syncRoles([Peran::SuperAdmin->value]);
    }

    protected function surel(string $nama): string
    {
        return 'panduan.'.(self::AKUN[$nama] ?? $nama).'@'.User::DOMAIN_PANDUAN;
    }

    protected function kataSandi(): string
    {
        return (string) config('keuangan.panduan.password');
    }
}
