<?php

use App\Actions\Master\BuatTahunAnggaran;
use App\Enums\Peran;
use App\Models\Role;
use App\Models\TahunAnggaran;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Pengguna aktif dengan peran tertentu (seeder peran dijalankan bila belum).
 * Peran yang wajib MFA otomatis dianggap sudah mengaktifkan MFA kecuali $mfa = false.
 */
function penggunaDenganPeran(Peran|string $peran, array $atribut = [], bool $mfa = true): User
{
    if (Role::count() === 0) {
        test()->seed(PeranDanIzinSeeder::class);
    }

    $nilai = $peran instanceof Peran ? $peran->value : $peran;

    $pengguna = User::factory()->create($atribut)->assignRole($nilai);

    if ($mfa && $pengguna->wajibMfa()) {
        $pengguna->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    }

    return $pengguna;
}

/**
 * Tahun anggaran beserta 12 periode, dibuat lewat Action oleh admin-keuangan baru.
 */
function buatTahun(int $tahun = 2027): TahunAnggaran
{
    return app(BuatTahunAnggaran::class)->handle($tahun, penggunaDenganPeran(Peran::AdminKeuangan));
}

/**
 * Data kegiatan sah untuk SimpanDrafPengajuan.
 *
 * @return array<string, mixed>
 */
function dataKegiatan(string $tahunAnggaranId, array $timpa = []): array
{
    return [
        'tahun_anggaran_id' => $tahunAnggaranId,
        'judul_kegiatan' => 'Lokakarya Kurikulum',
        'deskripsi' => 'Penyusunan kurikulum prodi',
        'tanggal_mulai_kegiatan' => '2027-03-10',
        'tanggal_selesai_kegiatan' => '2027-03-11',
        'tempat' => 'Aula FKIP',
        'penanggung_jawab' => 'Dr. Penanggung Jawab',
        ...$timpa,
    ];
}

/**
 * Satu item RAB sah (nilai string desimal).
 *
 * @return array<string, mixed>
 */
function itemRab(string $akunId, string $volume = '2', string $harga = '150000', array $timpa = []): array
{
    return [
        'akun_id' => $akunId,
        'uraian' => 'Konsumsi peserta',
        'rincian_volume' => null,
        'volume' => $volume,
        'satuan' => 'OH',
        'harga_satuan' => $harga,
        ...$timpa,
    ];
}
