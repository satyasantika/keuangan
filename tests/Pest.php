<?php

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Master\BuatTahunAnggaran;
use App\Actions\Master\TetapkanPagu;
use App\Actions\Master\UbahStatusTahunAnggaran;
use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Actions\Spj\CatatRealisasi;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Enums\StatusTahunAnggaran;
use App\Models\Akun;
use App\Models\JenisPencairan;
use App\Models\Pagu;
use App\Models\Pengajuan;
use App\Models\Role;
use App\Models\Spj;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitContohSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

/**
 * Konteks anggaran siap pakai: unit contoh, pengaturan bawaan, tahun 2027 berjalan, pagu PMAT ditetapkan.
 */
function siapkanAnggaran(string $jumlahPagu = '100000000.00'): object
{
    Http::fake(['*' => Http::response('', 403)]);
    test()->seed([UnitContohSeeder::class, PengaturanSeeder::class]);

    $keu = penggunaDenganPeran(Peran::AdminKeuangan);
    $wadek = penggunaDenganPeran(Peran::WadekKeuangan);
    $tahun = app(BuatTahunAnggaran::class)->handle(2027, $keu);
    app(UbahStatusTahunAnggaran::class)->handle($tahun, StatusTahunAnggaran::Berjalan, $keu);

    $pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $pbio = Unit::where('kode', 'PBIO')->firstOrFail();

    $pagu = Pagu::factory()->create(['tahun_anggaran_id' => $tahun->id, 'unit_id' => $pmat->id, 'jumlah' => $jumlahPagu, 'dibuat_oleh' => $keu->id]);
    app(TetapkanPagu::class)->handle($pagu, $wadek);

    return (object) [
        'tahun' => $tahun->fresh(),
        'pmat' => $pmat,
        'pbio' => $pbio,
        'pagu' => $pagu->fresh(),
        'keu' => $keu,
        'wadek' => $wadek,
        'adminPmat' => penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $pmat->id]),
        'kepalaPmat' => penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $pmat->id]),
        'bpp' => penggunaDenganPeran(Peran::Bpp),
        'akun' => Akun::factory()->create(),
    ];
}

/**
 * Pengajuan draf milik admin-unit PMAT beserta tautan TOR (total = volume × harga).
 */
function buatPengajuanDraf(object $k, string $volume = '2', string $harga = '150000', ?User $oleh = null, bool $denganTor = true): Pengajuan
{
    $oleh ??= $k->adminPmat;
    $pengajuan = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($k->tahun->id), [itemRab($k->akun->id, $volume, $harga)], $oleh);

    if ($denganTor) {
        app(SimpanTautanBerkas::class)->handle($pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorContoh/view', 'TOR', false, $oleh);
    }

    return $pengajuan->fresh();
}

/**
 * Pengajuan yang sudah diajukan lewat Action.
 */
function buatPengajuanDiajukan(object $k, string $volume = '2', string $harga = '150000'): Pengajuan
{
    return app(AjukanPengajuan::class)->handle(buatPengajuanDraf($k, $volume, $harga), $k->adminPmat)->fresh();
}

/**
 * Pengajuan yang sudah disetujui penuh (kepala unit → keuangan → wadek).
 */
function buatPengajuanDisetujui(object $k, string $volume = '2', string $harga = '150000', array $kegiatan = []): Pengajuan
{
    $pengajuan = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($k->tahun->id, $kegiatan), [itemRab($k->akun->id, $volume, $harga)], $k->adminPmat);
    app(SimpanTautanBerkas::class)->handle($pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorContoh/view', 'TOR', false, $k->adminPmat);
    $pengajuan = app(AjukanPengajuan::class)->handle($pengajuan->fresh(), $k->adminPmat);
    $pengajuan = app(VerifikasiPengajuan::class)->handle($pengajuan, $k->kepalaPmat);
    $pengajuan = app(VerifikasiPengajuan::class)->handle($pengajuan, $k->keu);

    return app(SetujuiPengajuan::class)->handle($pengajuan, $k->wadek)->fresh();
}

/**
 * Data pencairan sah beserta satu tautan bukti terkonfirmasi.
 *
 * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
 */
function dataPencairan(string $jumlah, string $tanggal = '2027-06-15', bool $konfirmasi = true): array
{
    $jenis = JenisPencairan::firstOrCreate(['kode' => 'LS'], ['nama' => 'Pembayaran Langsung']);

    return [
        ['jenis_pencairan_id' => $jenis->id, 'tanggal' => $tanggal, 'jumlah' => $jumlah, 'nomor_bukti' => 'KW-001', 'penerima' => 'Penyedia'],
        [['label' => 'Bukti transfer', 'url' => 'https://drive.google.com/file/d/1Bukti'.Str::random(6).'/view', 'akses_terbatas_dikonfirmasi' => $konfirmasi]],
    ];
}

/**
 * Pengajuan yang sudah dicairkan penuh (2 × 150.000 = 300.000) pada 15 Juni 2027.
 */
function buatPengajuanDicairkan(object $k, string $volume = '2', string $harga = '150000'): Pengajuan
{
    $pengajuan = buatPengajuanDisetujui($k, $volume, $harga);
    [$data, $tautan] = dataPencairan((string) $pengajuan->total_disetujui);
    app(CatatPencairan::class)->handle($pengajuan, $data, $tautan, $k->bpp);

    return $pengajuan->fresh();
}

/**
 * Data realisasi sah untuk item RAB aktif pertama, dengan tautan kuitansi terkonfirmasi.
 *
 * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
 */
function dataRealisasi(Pengajuan $pengajuan, string $volume = '1', string $harga = '150000', ?string $driveId = null, string $tanggal = '2027-06-16'): array
{
    $item = $pengajuan->rabAktif()->firstOrFail()->item()->firstOrFail();
    $driveId ??= '1Kw'.Str::random(10);

    return [
        ['rab_item_id' => $item->id, 'tanggal_bukti' => $tanggal, 'nomor_bukti' => 'KW-01', 'uraian' => 'Konsumsi', 'penerima' => 'Katering', 'volume' => $volume, 'harga_satuan' => $harga],
        [['jenis' => 'kuitansi', 'label' => 'Kuitansi konsumsi', 'url' => "https://drive.google.com/file/d/{$driveId}/view", 'akses_terbatas_dikonfirmasi' => true]],
    ];
}

/**
 * Pengajuan dicairkan 300.000 dengan realisasi sejumlah $bruto (volume 1) → SPJ draf.
 */
function buatSpjDenganRealisasi(object $k, string $bruto = '300000'): Spj
{
    $pengajuan = buatPengajuanDicairkan($k);
    [$data, $tautan] = dataRealisasi($pengajuan, '1', $bruto);

    return app(CatatRealisasi::class)->handle($pengajuan, $data, $tautan, $k->adminPmat)->spj->fresh();
}
