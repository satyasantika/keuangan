<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data UAT HANYA untuk APP_ENV=staging: pengguna uji per peran, tahun anggaran, pagu, dan pengajuan pada
 * berbagai status (memakai skenario DemoSeeder).
 *
 * UAT_PASSWORD  : kata sandi awal semua akun uji (wajib, ganti setelah login pertama).
 * UAT_EMAILS    : pemetaan akun → surel penguji, mis. "admin-keuangan=ani@unsil.ac.id,wadek=budi@unsil.ac.id,
 *                 admin-pmat=citra@unsil.ac.id". Akun yang tidak dipetakan memakai uat.<akun>@contoh.test.
 *                 Nama akun: admin-keuangan, wadek, bpp, pimpinan, kepala-pmat, admin-pmat, kepala-pbio, admin-pbio,
 *                 kepala-pbi, admin-pbi.
 */
class UatSeeder extends DemoSeeder
{
    public function run(): void
    {
        if (! app()->environment('staging')) {
            throw new RuntimeException('UatSeeder hanya boleh dijalankan di APP_ENV=staging.');
        }

        if (blank(config('keuangan.uat.password'))) {
            throw new RuntimeException('UAT_PASSWORD wajib diisi di .env server uji.');
        }

        parent::run();
    }

    protected function lingkunganDiizinkan(): bool
    {
        return app()->environment('staging');
    }

    protected function surel(string $nama): string
    {
        return $this->pemetaanSurel()[$nama] ?? 'uat.'.$nama.'@contoh.test';
    }

    protected function kataSandi(): string
    {
        return (string) config('keuangan.uat.password');
    }

    /**
     * @return array<string, string>
     */
    private function pemetaanSurel(): array
    {
        $hasil = [];

        foreach (array_filter(array_map('trim', explode(',', (string) config('keuangan.uat.emails')))) as $pasangan) {
            [$akun, $surel] = array_pad(array_map('trim', explode('=', $pasangan, 2)), 2, '');

            if ($akun !== '' && filter_var($surel, FILTER_VALIDATE_EMAIL)) {
                $hasil[Str::lower($akun)] = Str::lower($surel);
            }
        }

        return $hasil;
    }
}
