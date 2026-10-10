<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran dan izin sesuai matriks izin per peran. Cakupan "(u)" tetap diberi izin;
 * pembatasan unit dilakukan Policy. Idempoten.
 *
 * Kode aksi: L lihat, B buat, U ubah, H hapus, S setujui, E ekspor.
 */
class PeranDanIzinSeeder extends Seeder
{
    private const AKSI = [
        'L' => 'lihat',
        'B' => 'buat',
        'U' => 'ubah',
        'H' => 'hapus',
        'S' => 'setujui',
        'E' => 'ekspor',
    ];

    /**
     * @return array<string, array<string, string>> modul => [peran => kode aksi]
     */
    public static function matriks(): array
    {
        return [
            'pengguna' => ['super-admin' => 'LBUH', 'admin-keuangan' => 'L', 'wadek-keuangan' => 'L'],
            'audit' => ['super-admin' => 'LE', 'admin-keuangan' => 'LE', 'bpp' => 'L', 'wadek-keuangan' => 'L', 'pimpinan' => 'L'],
            'master' => ['super-admin' => 'LBUH', 'admin-keuangan' => 'LBUHE', 'bpp' => 'L', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'admin-unit' => 'L', 'pimpinan' => 'L'],
            'pengaturan' => ['super-admin' => 'LU', 'admin-keuangan' => 'LU', 'bpp' => 'L', 'wadek-keuangan' => 'L'],
            'pagu' => ['super-admin' => 'L', 'admin-keuangan' => 'LBUE', 'bpp' => 'L', 'wadek-keuangan' => 'LSE', 'kepala-unit' => 'L', 'admin-unit' => 'L', 'pimpinan' => 'LE'],
            'pengajuan' => ['super-admin' => 'L', 'admin-keuangan' => 'LE', 'bpp' => 'L', 'wadek-keuangan' => 'LE', 'kepala-unit' => 'LE', 'admin-unit' => 'LBUHE', 'pimpinan' => 'LE'],
            'persetujuan' => ['admin-keuangan' => 'S', 'wadek-keuangan' => 'S', 'kepala-unit' => 'S'],
            'revisi' => ['super-admin' => 'L', 'admin-keuangan' => 'LS', 'bpp' => 'L', 'wadek-keuangan' => 'LS', 'kepala-unit' => 'L', 'admin-unit' => 'LBU', 'pimpinan' => 'L'],
            'pencairan' => ['super-admin' => 'L', 'admin-keuangan' => 'LE', 'bpp' => 'LBUE', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'admin-unit' => 'L', 'pimpinan' => 'L'],
            'spj' => ['super-admin' => 'L', 'admin-keuangan' => 'LSE', 'bpp' => 'LSE', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'admin-unit' => 'LBU', 'pimpinan' => 'L'],
            'pajak' => ['super-admin' => 'L', 'admin-keuangan' => 'LE', 'bpp' => 'LUE', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'admin-unit' => 'LBU', 'pimpinan' => 'L'],
            'pengembalian' => ['super-admin' => 'L', 'admin-keuangan' => 'LS', 'bpp' => 'LSE', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'admin-unit' => 'LB', 'pimpinan' => 'L'],
            'periode' => ['super-admin' => 'L', 'admin-keuangan' => 'LS', 'bpp' => 'L', 'wadek-keuangan' => 'LS', 'pimpinan' => 'L'],
            'laporan' => ['super-admin' => 'LE', 'admin-keuangan' => 'LE', 'bpp' => 'LE', 'wadek-keuangan' => 'LE', 'kepala-unit' => 'LE', 'admin-unit' => 'LE', 'pimpinan' => 'LE'],
            'dasbor' => ['super-admin' => 'L', 'admin-keuangan' => 'L', 'bpp' => 'L', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'admin-unit' => 'L', 'pimpinan' => 'L'],
            'impor' => ['super-admin' => 'L', 'admin-keuangan' => 'LBH', 'bpp' => 'L', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'pimpinan' => 'L'],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $izinPerPeran = array_fill_keys(array_column(Peran::cases(), 'value'), []);

        foreach (self::matriks() as $modul => $perPeran) {
            foreach ($perPeran as $peran => $kode) {
                foreach (str_split($kode) as $huruf) {
                    $nama = $modul.'.'.self::AKSI[$huruf];
                    Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
                    $izinPerPeran[$peran][] = $nama;
                }
            }
        }

        foreach (Peran::cases() as $peran) {
            $role = Role::firstOrCreate(['name' => $peran->value, 'guard_name' => 'web']);
            $role->syncPermissions($izinPerPeran[$peran->value]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
