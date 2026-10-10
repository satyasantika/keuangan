<?php

namespace App\Support;

use App\Models\Akun;
use App\Models\JenisPencairan;
use App\Models\KategoriPenggunaanDana;
use App\Models\TarifPajak;

/**
 * Butir "perlu verifikasi" yang masih terbuka (ADR 0002, master pencairan/pajak/akun, kategori akreditasi) untuk
 * ditampilkan sebagai lencana di halaman Pengaturan. Tidak ada nilai regulasi yang diisi oleh aplikasi.
 */
final class DaftarPerluVerifikasi
{
    /**
     * @return list<array{judul: string, jumlah: int, keterangan: string}>
     */
    public static function terbuka(): array
    {
        $butir = [
            [
                'judul' => 'Parameter ADR 0002',
                'jumlah' => self::parameterAdrTerbuka(),
                'keterangan' => 'Baris berstatus "perlu verifikasi" di docs/keputusan/0002-parameter-keuangan.md.',
            ],
            [
                'judul' => 'Jenis pencairan',
                'jumlah' => JenisPencairan::query()->where('perlu_verifikasi', true)->count(),
                'keterangan' => 'Istilah UP/TUP/LS belum dikonfirmasi bagian keuangan.',
            ],
            [
                'judul' => 'Tarif pajak referensi',
                'jumlah' => TarifPajak::query()->where('perlu_verifikasi', true)->count(),
                'keterangan' => 'Tarif & dasar hukum diisi dan dikonfirmasi bagian keuangan (sistem tidak menghitung pajak).',
            ],
            [
                'judul' => 'Akun BAS contoh',
                'jumlah' => Akun::query()->where('sumber', 'contoh')->count(),
                'keterangan' => 'Ganti dengan impor BAS resmi.',
            ],
            [
                'judul' => 'Kategori penggunaan dana (akreditasi)',
                'jumlah' => KategoriPenggunaanDana::query()->count() > 0 ? 1 : 0,
                'keterangan' => 'Format instrumen LKPS/LAMDIK perlu dicek.',
            ],
        ];

        return array_values(array_filter($butir, fn (array $b): bool => $b['jumlah'] > 0));
    }

    /**
     * Jumlah baris tabel ADR 0002 yang status-nya masih memuat "perlu verifikasi".
     */
    public static function parameterAdrTerbuka(?string $berkas = null): int
    {
        $berkas ??= base_path('docs/keputusan/0002-parameter-keuangan.md');

        if (! is_file($berkas)) {
            return 0;
        }

        $jumlah = 0;

        foreach (file($berkas) ?: [] as $baris) {
            if (preg_match('/^\|\s*\d+\s*\|/', $baris) !== 1) {
                continue;
            }

            $kolom = array_map('trim', explode('|', trim($baris, " \n|")));

            if (isset($kolom[4]) && stripos($kolom[4], 'perlu verifikasi') !== false) {
                $jumlah++;
            }
        }

        return $jumlah;
    }
}
