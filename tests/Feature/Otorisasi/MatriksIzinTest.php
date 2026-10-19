<?php

use App\Enums\Peran;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitContohSeeder;

/**
 * Matriks izin per peran diuji per peran (MFA dianggap aktif) terhadap halaman & Resource Filament.
 */
const SEMUA_PERAN = ['super-admin', 'admin-keuangan', 'bpp', 'wadek-keuangan', 'kepala-unit', 'admin-unit', 'pimpinan'];

function penggunaMatriks(string $peran): User
{
    $unit = in_array($peran, ['kepala-unit', 'admin-unit'], true) ? Unit::query()->where('kode', 'PMAT')->value('id') : null;

    return penggunaDenganPeran($peran, ['unit_id' => $unit]);
}

/**
 * @return array<string, array{0: string, 1: string, 2: bool}>
 */
function datasetHalaman(): array
{
    $halaman = [
        // path => peran yang boleh membuka
        'panel' => SEMUA_PERAN,
        'panel/users' => ['super-admin', 'admin-keuangan', 'wadek-keuangan'],
        'panel/users/create' => ['super-admin'],
        'panel/tahun-anggaran' => SEMUA_PERAN,
        'panel/tahun-anggaran/create' => ['super-admin', 'admin-keuangan'],
        'panel/unit' => SEMUA_PERAN,
        'panel/unit/create' => ['super-admin', 'admin-keuangan'],
        'panel/akun' => SEMUA_PERAN,
        'panel/akun/create' => ['super-admin', 'admin-keuangan'],
        'panel/jenis-pajak' => SEMUA_PERAN,
        'panel/jenis-pajak/create' => ['super-admin', 'admin-keuangan'],
        'panel/jenis-pencairan' => SEMUA_PERAN,
        'panel/kategori-penggunaan-dana' => SEMUA_PERAN,
        'panel/pengaturan' => ['super-admin', 'admin-keuangan', 'bpp', 'wadek-keuangan'],
        'panel/pagu' => SEMUA_PERAN,
        'panel/pagu/create' => ['admin-keuangan'],
        'panel/pengajuan' => SEMUA_PERAN,
        'panel/pengajuan/create' => ['admin-unit'],
        'panel/revisi-rab' => SEMUA_PERAN,
        'panel/pencairan' => SEMUA_PERAN,
        'panel/spj' => SEMUA_PERAN,
        'panel/pengembalian' => SEMUA_PERAN,
        'panel/laporan' => SEMUA_PERAN,
        'panel/riwayat-ekspor' => SEMUA_PERAN,
        'panel/rekap-honor' => ['super-admin', 'admin-keuangan', 'bpp', 'wadek-keuangan', 'kepala-unit', 'pimpinan'],
    ];

    $data = [];

    foreach ($halaman as $path => $boleh) {
        foreach (SEMUA_PERAN as $peran) {
            $data["{$peran} → /{$path}"] = [$peran, $path, in_array($peran, $boleh, true)];
        }
    }

    return $data;
}

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitContohSeeder::class]);
});

test('halaman panel mengikuti matriks izin', function (string $peran, string $path, bool $boleh) {
    $respons = $this->actingAs(penggunaMatriks($peran))->get('/'.$path);

    $boleh ? $respons->assertOk() : $respons->assertForbidden();
})->with(datasetHalaman());

test('izin per peran sama persis dengan matriks izin', function (string $peran) {
    $matriks = [
        // Cakupan commit `auth` memakai prefiks izin `pengguna`; baris "Pengaturan" memakai prefiks `pengaturan`.
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
        'laporan' => array_fill_keys(SEMUA_PERAN, 'LE'),
        'dasbor' => array_fill_keys(SEMUA_PERAN, 'L'),
        'impor' => ['super-admin' => 'L', 'admin-keuangan' => 'LBH', 'bpp' => 'L', 'wadek-keuangan' => 'L', 'kepala-unit' => 'L', 'pimpinan' => 'L'],
    ];
    $aksi = ['L' => 'lihat', 'B' => 'buat', 'U' => 'ubah', 'H' => 'hapus', 'S' => 'setujui', 'E' => 'ekspor'];
    $pengguna = penggunaMatriks($peran);

    foreach ($matriks as $modul => $perPeran) {
        foreach ($aksi as $huruf => $nama) {
            $harapan = str_contains($perPeran[$peran] ?? '', $huruf);
            expect($pengguna->can("{$modul}.{$nama}"))->toBe($harapan, "{$peran} {$modul}.{$nama}");
        }
    }
})->with(SEMUA_PERAN);
