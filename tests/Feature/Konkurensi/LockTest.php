<?php

use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Enums\StatusPengajuan;
use App\Exceptions\AksiSedangDiprosesException;
use App\Support\DenganKunci;
use App\Support\Penomoran;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BR-05/BR-09 dengan store cache `database` SUNGGUHAN (tabel cache_locks), bukan array (Profil Ringan: tanpa Redis).
 * Store memakai koneksi sendiri ('kunci') di luar transaksi RefreshDatabase agar kunci terlihat oleh proses lain.
 * Proses paralel memakai pcntl_fork; anak membuat koneksi DB baru (tidak memakai koneksi milik induk) dan diakhiri
 * SIGKILL agar tidak menjalankan destructor/shutdown PHPUnit. Hasil anak dikumpulkan lewat berkas sementara.
 */
beforeEach(function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia.');
    }

    config([
        'database.connections.kunci' => config('database.connections.'.config('database.default')),
        'cache.stores.kunci' => ['driver' => 'database', 'connection' => 'kunci', 'table' => 'cache', 'lock_connection' => 'kunci', 'lock_table' => 'cache_locks'],
        'keuangan.lock_store' => 'kunci',
    ]);
    $this->kunciUji = 'uji-'.Str::uuid7();
});

afterEach(function () {
    DB::connection('kunci')->table('cache_locks')->delete();
    DB::purge('kunci');
});

/**
 * Menjalankan $kerja di $jumlah proses anak; mengembalikan baris hasil yang ditulis anak.
 *
 * @param  Closure(int, string): void  $kerja  menerima nomor anak & jalur berkas hasil
 * @return list<string>
 */
function jalankanParalel(int $jumlah, Closure $kerja): array
{
    $berkas = sys_get_temp_dir().'/uji-hasil-'.Str::uuid7();
    $anak = [];

    for ($i = 0; $i < $jumlah; $i++) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            try {
                $kerja($i, $berkas);
            } catch (Throwable $e) {
                tulisHasil($berkas, 'galat:'.$e::class.':'.mb_substr($e->getMessage(), 0, 120));
            } finally {
                posix_kill(getmypid(), SIGKILL);
            }
        }

        $anak[] = $pid;
    }

    foreach ($anak as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $hasil = is_file($berkas) ? array_values(array_filter(explode("\n", (string) file_get_contents($berkas)))) : [];
    @unlink($berkas);

    return $hasil;
}

function tulisHasil(string $berkas, string $baris): void
{
    file_put_contents($berkas, $baris."\n", FILE_APPEND | LOCK_EX);
}

test('dua proses berebut satu kunci → hanya satu yang berjalan', function () {
    $kunci = $this->kunciUji;

    $hasil = jalankanParalel(5, function (int $i, string $berkas) use ($kunci): void {
        try {
            DenganKunci::jalankan($kunci, function () use ($berkas): void {
                usleep(1_500_000);
                tulisHasil($berkas, 'sukses');
            }, ttl: 10, tunggu: 0);
        } catch (AksiSedangDiprosesException) {
            tulisHasil($berkas, 'ditolak');
        }
    });

    $hitung = array_count_values($hasil);
    ksort($hitung);

    expect($hitung)->toBe(['ditolak' => 4, 'sukses' => 1]);
})->group('konkurensi');

test('penomoran 100 panggilan paralel → 100 nomor unik', function () {
    $jenis = 'UJI'.Str::upper(Str::random(5));
    $koneksi = config('database.default');
    config(['database.connections.anak' => config("database.connections.{$koneksi}")]);

    try {
        $hasil = jalankanParalel(10, function (int $i, string $berkas) use ($jenis): void {
            // Koneksi DB baru; koneksi induk (dalam transaksi RefreshDatabase) tidak disentuh.
            DB::setDefaultConnection('anak');

            for ($n = 0; $n < 10; $n++) {
                // Kunci ditolak (AksiSedangDiprosesException) saat beban tinggi adalah perilaku sah: ulangi.
                for ($coba = 0; ; $coba++) {
                    try {
                        tulisHasil($berkas, Penomoran::berikutnya($jenis, 2027, 'X/{tahun}/{urut}'));
                        break;
                    } catch (AksiSedangDiprosesException $e) {
                        if ($coba >= 30) {
                            throw $e;
                        }
                    }
                }
            }
        });

        expect($hasil)->toHaveCount(100)
            ->and(array_unique($hasil))->toHaveCount(100)
            ->and(collect($hasil)->filter(fn (string $h) => str_starts_with($h, 'galat:'))->all())->toBe([]);
    } finally {
        DB::connection('anak')->table('penomoran')->where('jenis', $jenis)->delete();
        DB::purge('anak');
    }
})->group('konkurensi');

test('AjukanPengajuan & SetujuiPengajuan saat kunci record dipegang proses lain → ditolak, lalu berhasil', function () {
    $this->travelTo('2027-06-20 10:00:00');
    $k = siapkanAnggaran();
    $draf = buatPengajuanDraf($k);
    $diajukan = buatPengajuanDiajukan($k);
    $diajukan = app(VerifikasiPengajuan::class)->handle(app(VerifikasiPengajuan::class)->handle($diajukan, $k->kepalaPmat), $k->keu);

    // Lock::block() memakai Carbon::now(); waktu harus berjalan agar batas tunggu tercapai.
    $this->travelBack();

    foreach ([
        [$draf, fn () => app(AjukanPengajuan::class)->handle($draf->fresh(), $k->adminPmat), StatusPengajuan::Diajukan],
        [$diajukan, fn () => app(SetujuiPengajuan::class)->handle($diajukan->fresh(), $k->wadek), StatusPengajuan::Disetujui],
    ] as [$pengajuan, $aksi, $harapan]) {
        $lock = Cache::store('kunci')->lock("lock:aksi:pengajuan:{$pengajuan->id}", 15);
        expect($lock->get())->toBeTrue();
        expect($aksi)->toThrow(AksiSedangDiprosesException::class);
        $lock->release();

        expect($aksi()->status)->toBe($harapan);
    }
})->group('konkurensi');
