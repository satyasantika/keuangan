<?php

namespace Database\Seeders;

use App\Actions\Master\BuatTahunAnggaran;
use App\Actions\Master\TetapkanPagu;
use App\Actions\Master\UbahStatusTahunAnggaran;
use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Actions\Persetujuan\KembalikanPengajuan;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\TolakPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\TerimaSpj;
use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Enums\StatusTahunAnggaran;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Akun;
use App\Models\JenisPencairan;
use App\Models\Pagu;
use App\Models\Pengajuan;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * Data demo HANYA untuk environment local: pengguna per peran (*@demo.test / "password"),
 * tahun anggaran berjalan, pagu ditetapkan untuk unit contoh, dan 10 pengajuan draf/diajukan.
 * Tautan TOR berupa URL Drive fiktif berformat valid (tidak diperiksa ke Google).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        // Tautan demo fiktif tidak diperiksa ke Google.
        Bus::fake([PeriksaTautanBerkas::class]);

        $this->call([PeranDanIzinSeeder::class, PengaturanSeeder::class, JenisPencairanSeeder::class, UnitContohSeeder::class, AkunContohSeeder::class, KategoriPenggunaanDanaContohSeeder::class]);

        $unit = Unit::query()->whereIn('kode', ['PMAT', 'PBIO', 'PBI'])->get()->keyBy('kode');

        $keu = $this->pengguna('admin-keuangan', 'Admin Keuangan Demo', Peran::AdminKeuangan);
        $wadek = $this->pengguna('wadek', 'Wadek Keuangan Demo', Peran::WadekKeuangan);
        $bpp = $this->pengguna('bpp', 'BPP Demo', Peran::Bpp);
        $this->pengguna('pimpinan', 'Dekan Demo', Peran::Pimpinan);

        $adminUnit = [];
        foreach ($unit as $kode => $u) {
            $slug = Str::lower($kode);
            $kepala = $this->pengguna("kepala-{$slug}", "Kepala {$kode} Demo", Peran::KepalaUnit, $u->id);
            $u->update(['kepala_user_id' => $kepala->id]);
            $adminUnit[$kode] = $this->pengguna("admin-{$slug}", "Admin {$kode} Demo", Peran::AdminUnit, $u->id);
        }

        $tahunBerjalan = (int) now()->year;
        $tahun = TahunAnggaran::query()->where('tahun', $tahunBerjalan)->first()
            ?? app(BuatTahunAnggaran::class)->handle($tahunBerjalan, $keu, 'Data demo');

        if ($tahun->status === StatusTahunAnggaran::Perencanaan) {
            $tahun = app(UbahStatusTahunAnggaran::class)->handle($tahun, StatusTahunAnggaran::Berjalan, $keu);
        }

        foreach ($unit as $u) {
            $pagu = Pagu::query()->firstOrCreate(
                ['tahun_anggaran_id' => $tahun->id, 'unit_id' => $u->id],
                ['jumlah' => '150000000.00', 'sumber_dana' => 'Demo', 'dibuat_oleh' => $keu->id],
            );

            if (! $pagu->berlaku()) {
                app(TetapkanPagu::class)->handle($pagu, $wadek, 'Penetapan data demo');
            }
        }

        if (Pengajuan::query()->where('tahun_anggaran_id', $tahun->id)->exists()) {
            return;
        }

        $akun = Akun::query()->where('aktif', true)->orderBy('kode')->get();
        $judul = ['Lokakarya Kurikulum', 'Seminar Nasional Pendidikan', 'Pelatihan Asesor', 'Kuliah Umum', 'Studi Banding',
            'Pengabdian Masyarakat', 'Penyusunan Bahan Ajar', 'Rapat Kerja Prodi', 'Workshop Penelitian', 'Lomba Inovasi Pembelajaran'];

        foreach ($judul as $i => $nama) {
            $kode = ['PMAT', 'PBIO', 'PBI'][$i % 3];
            $oleh = $adminUnit[$kode];
            $bulan = min(12, max((int) now()->month, 1) + ($i % 2));
            $mulai = now()->setDate($tahunBerjalan, $bulan, 5 + $i);

            $pengajuan = app(SimpanDrafPengajuan::class)->handle([
                'tahun_anggaran_id' => $tahun->id,
                'judul_kegiatan' => $nama,
                'deskripsi' => 'Data demo — bukan kegiatan sungguhan.',
                'tanggal_mulai_kegiatan' => $mulai->toDateString(),
                'tanggal_selesai_kegiatan' => $mulai->copy()->addDay()->toDateString(),
                'tempat' => 'Kampus Unsil',
                'penanggung_jawab' => "Penanggung jawab {$kode}",
            ], [
                ['akun_id' => $akun[$i % $akun->count()]->id, 'uraian' => 'Konsumsi peserta', 'volume' => (string) (20 + $i), 'satuan' => 'OK', 'harga_satuan' => '35000'],
                ['akun_id' => $akun[($i + 1) % $akun->count()]->id, 'uraian' => 'Honor narasumber', 'volume' => '2', 'satuan' => 'OJ', 'harga_satuan' => '900000'],
            ], $oleh);

            app(PenyimpananBerkas::class)->simpan($pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1Demo'.Str::random(20).'/view', "TOR {$nama}", false, $oleh);

            if ($i % 2 === 1 || $i === 0) {
                $pengajuan = app(AjukanPengajuan::class)->handle($pengajuan, $oleh);
            }

            $kepala = User::query()->whereKey($unit[$kode]->kepala_user_id)->firstOrFail();
            $this->majukan($pengajuan, $i, $kepala, $keu, $wadek);
        }

        // Pengajuan yang sudah selesai dan yang SPJ-nya terlambat (butuh tanggal lampau di tahun berjalan).
        if (now()->dayOfYear > 60) {
            $kepalaPmat = User::query()->whereKey($unit['PMAT']->kepala_user_id)->firstOrFail();
            $setujui = fn (Pengajuan $p): Pengajuan => app(SetujuiPengajuan::class)->handle(
                app(VerifikasiPengajuan::class)->handle(app(VerifikasiPengajuan::class)->handle($p, $kepalaPmat), $keu), $wadek, 'Disetujui (demo)');

            $selesai = $setujui($this->pengajuanLampau('Pelatihan Penulisan Artikel', 20, $tahun->id, $adminUnit['PMAT'], $akun->first()));
            $this->cairkan($selesai, 18, $bpp);
            $this->spjLengkap($selesai->fresh(), 19, $adminUnit['PMAT'], $bpp);

            $terlambat = $setujui($this->pengajuanLampau('Bimbingan Teknis Laboratorium', 50, $tahun->id, $adminUnit['PMAT'], $akun->first()));
            $this->cairkan($terlambat, 45, $bpp);
        }
    }

    private function pengajuanLampau(string $judul, int $hariLalu, string $tahunId, User $oleh, Akun $akun): Pengajuan
    {
        $mulai = now()->subDays($hariLalu);
        $pengajuan = app(SimpanDrafPengajuan::class)->handle([
            'tahun_anggaran_id' => $tahunId,
            'judul_kegiatan' => $judul,
            'deskripsi' => 'Data demo — bukan kegiatan sungguhan.',
            'tanggal_mulai_kegiatan' => $mulai->toDateString(),
            'tanggal_selesai_kegiatan' => $mulai->copy()->addDay()->toDateString(),
            'tempat' => 'Kampus Unsil',
            'penanggung_jawab' => 'Penanggung jawab PMAT',
        ], [
            ['akun_id' => $akun->id, 'uraian' => 'Konsumsi peserta', 'volume' => '30', 'satuan' => 'OK', 'harga_satuan' => '35000'],
        ], $oleh);

        app(PenyimpananBerkas::class)->simpan($pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1Demo'.Str::random(20).'/view', "TOR {$judul}", false, $oleh);

        return app(AjukanPengajuan::class)->handle($pengajuan, $oleh);
    }

    private function cairkan(Pengajuan $pengajuan, int $hariLalu, User $bpp): void
    {
        app(CatatPencairan::class)->handle($pengajuan->fresh() ?? $pengajuan, [
            'jenis_pencairan_id' => JenisPencairan::query()->where('kode', 'LS')->value('id'),
            'tanggal' => now()->subDays($hariLalu)->toDateString(),
            'jumlah' => (string) $pengajuan->total_disetujui,
            'nomor_bukti' => 'DEMO-'.Str::upper(Str::random(6)),
            'penerima' => 'Penyedia demo',
        ], [
            ['label' => 'Bukti transfer (demo)', 'url' => 'https://drive.google.com/file/d/1Demo'.Str::random(20).'/view', 'akses_terbatas_dikonfirmasi' => true],
        ], $bpp);
    }

    private function spjLengkap(Pengajuan $pengajuan, int $hariLalu, User $oleh, User $bpp): void
    {
        $item = $pengajuan->rabAktif()->firstOrFail()->item()->firstOrFail();
        $realisasi = app(CatatRealisasi::class)->handle($pengajuan, [
            'rab_item_id' => $item->id,
            'tanggal_bukti' => now()->subDays($hariLalu)->toDateString(),
            'nomor_bukti' => 'KW-DEMO',
            'uraian' => 'Konsumsi peserta',
            'volume' => (string) $item->volume,
            'harga_satuan' => (string) $item->harga_satuan,
        ], [
            ['jenis' => 'kuitansi', 'label' => 'Kuitansi (demo)', 'url' => 'https://drive.google.com/file/d/1Demo'.Str::random(20).'/view', 'akses_terbatas_dikonfirmasi' => true],
        ], $oleh);

        $spj = app(AjukanSpj::class)->handle($realisasi->spj, $oleh, true);
        app(TerimaSpj::class)->handle($spj, $bpp, 'Lengkap (demo)');
    }

    /**
     * Sebar pengajuan ke setiap status persetujuan: 0 dikembalikan, 1 diajukan, 3 diverifikasi unit,
     * 5 diverifikasi keuangan, 7 disetujui, 9 ditolak; sisanya draf.
     */
    private function majukan(Pengajuan $pengajuan, int $i, User $kepala, User $keu, User $wadek): void
    {
        $verifikasi = app(VerifikasiPengajuan::class);

        match ($i) {
            0 => app(KembalikanPengajuan::class)->handle($pengajuan, $kepala, 'Lengkapi rincian volume (demo)'),
            3 => $verifikasi->handle($pengajuan, $kepala),
            5 => $verifikasi->handle($verifikasi->handle($pengajuan, $kepala), $keu),
            7 => app(SetujuiPengajuan::class)->handle($verifikasi->handle($verifikasi->handle($pengajuan, $kepala), $keu), $wadek, 'Disetujui (demo)'),
            9 => app(TolakPengajuan::class)->handle($pengajuan, $kepala, 'Tidak sesuai rencana unit (demo)'),
            default => null,
        };
    }

    private function pengguna(string $nama, string $namaLengkap, Peran $peran, ?string $unitId = null): User
    {
        $pengguna = User::query()->firstOrCreate(
            ['email' => "{$nama}@demo.test"],
            ['name' => $namaLengkap, 'password' => 'password', 'aktif' => true, 'unit_id' => $unitId],
        );

        $pengguna->forceFill(['email_verified_at' => $pengguna->email_verified_at ?? now()])->save();
        $pengguna->syncRoles([$peran->value]);

        return $pengguna;
    }
}
