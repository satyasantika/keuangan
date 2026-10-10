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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * BR-05/BR-09 dengan store Redis sungguhan. Dijalankan bila Redis tersedia (grup 'redis').
 * Proses paralel memakai pcntl_fork; anak tidak memakai koneksi DB/Redis milik induk dan diakhiri SIGKILL
 * agar tidak menjalankan destructor/shutdown PHPUnit.
 */
beforeEach(function () {
    $soket = @fsockopen((string) config('database.redis.default.host'), (int) config('database.redis.default.port'), $errno, $errstr, 1);

    if ($soket === false) {
        $this->markTestSkipped('Redis tidak tersedia.');
    }

    fclose($soket);
    config(['keuangan.lock_store' => 'redis']);
    $this->kunciUji = 'uji-'.Str::uuid7();
});

/**
 * Menjalankan $kerja di $jumlah proses anak; mengembalikan isi daftar Redis hasil.
 *
 * @param  Closure(int, string): void  $kerja  menerima nomor anak & nama daftar hasil
 * @return list<string>
 */
function jalankanParalel(int $jumlah, Closure $kerja): array
{
    $daftar = 'uji:hasil:'.Str::uuid7();
    $anak = [];

    for ($i = 0; $i < $jumlah; $i++) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            try {
                app('redis')->purge();
                Cache::forgetDriver('redis');
                $kerja($i, $daftar);
            } catch (Throwable $e) {
                Redis::rpush($daftar, 'galat:'.$e::class);
            } finally {
                posix_kill(getmypid(), SIGKILL);
            }
        }

        $anak[] = $pid;
    }

    foreach ($anak as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $hasil = Redis::lrange($daftar, 0, -1);
    Redis::del($daftar);

    return $hasil;
}

test('dua proses berebut satu kunci → hanya satu yang berjalan', function () {
    $kunci = $this->kunciUji;

    $hasil = jalankanParalel(5, function (int $i, string $daftar) use ($kunci): void {
        try {
            DenganKunci::jalankan($kunci, function () use ($daftar): void {
                usleep(1_500_000);
                Redis::rpush($daftar, 'sukses');
            }, ttl: 10, tunggu: 0);
        } catch (AksiSedangDiprosesException) {
            Redis::rpush($daftar, 'ditolak');
        }
    });

    $hitung = array_count_values($hasil);
    ksort($hitung);

    expect($hitung)->toBe(['ditolak' => 4, 'sukses' => 1]);
})->group('redis');

test('penomoran 100 panggilan paralel → 100 nomor unik', function () {
    $jenis = 'UJI'.Str::upper(Str::random(5));
    $koneksi = config('database.default');
    config(['database.connections.anak' => config("database.connections.{$koneksi}")]);

    try {
        $hasil = jalankanParalel(10, function (int $i, string $daftar) use ($jenis): void {
            // Koneksi DB baru; koneksi induk (dalam transaksi RefreshDatabase) tidak disentuh.
            DB::setDefaultConnection('anak');

            for ($n = 0; $n < 10; $n++) {
                Redis::rpush($daftar, Penomoran::berikutnya($jenis, 2027, 'X/{tahun}/{urut}'));
            }
        });

        expect($hasil)->toHaveCount(100)
            ->and(array_unique($hasil))->toHaveCount(100)
            ->and(collect($hasil)->filter(fn (string $h) => str_starts_with($h, 'galat:'))->all())->toBe([]);
    } finally {
        DB::connection('anak')->table('penomoran')->where('jenis', $jenis)->delete();
        DB::purge('anak');
    }
})->group('redis');

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
        $lock = Cache::store('redis')->lock("lock:aksi:pengajuan:{$pengajuan->id}", 15);
        expect($lock->get())->toBeTrue();
        expect($aksi)->toThrow(AksiSedangDiprosesException::class);
        $lock->release();

        expect($aksi()->status)->toBe($harapan);
    }
})->group('redis');
