<?php

use App\Filament\Imports\AkunImporter;
use App\Filament\Imports\UnitImporter;
use App\Jobs\BuatEksporLaporan;
use App\Jobs\ImporRekapHonor;
use App\Models\LaporanEkspor;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): dua antrean di database, worker dinyalakan penjadwal.
 */
test('koneksi database dan database-berat memakai after_commit dan retry_after di atas timeout worker', function () {
    $ringan = config('queue.connections.database');
    $berat = config('queue.connections.database-berat');

    expect($ringan['driver'])->toBe('database')
        ->and($ringan['after_commit'])->toBeTrue()
        ->and($ringan['retry_after'])->toBeGreaterThan(60)   // --timeout worker default
        ->and($berat['driver'])->toBe('database')
        ->and($berat['queue'])->toBe('berat')
        ->and($berat['after_commit'])->toBeTrue()
        ->and($berat['retry_after'])->toBeGreaterThan(900);  // --timeout worker berat
});

test('jadwal memuat dua worker, detak penjadwal, dan pemangkasan antrean', function () {
    $jadwal = collect(app(Schedule::class)->events())
        ->map(fn ($e) => (string) ($e->command ?? $e->description))
        ->implode("\n");

    expect($jadwal)
        ->toContain('queue:work database --queue=default --stop-when-empty')
        ->toContain('queue:work database-berat --queue=berat --stop-when-empty')
        ->toContain('detak-penjadwal')
        ->toContain('queue:prune-failed')
        ->toContain('queue:prune-batches')
        ->not->toContain('horizon');
});

test('ekspor laporan dan impor rekap honor memakai koneksi database-berat dan antrean berat', function () {
    $ekspor = new BuatEksporLaporan(new LaporanEkspor);
    $impor = new ImporRekapHonor('/tmp/x.xlsx', 'x.xlsx', new User);

    expect($ekspor->connection)->toBe('database-berat')->and($ekspor->queue)->toBe('berat')
        ->and($impor->connection)->toBe('database-berat')->and($impor->queue)->toBe('berat');
});

test('importer Filament diarahkan ke koneksi database-berat dan antrean berat', function () {
    foreach ([AkunImporter::class, UnitImporter::class] as $kelas) {
        $importer = (new ReflectionClass($kelas))->newInstanceWithoutConstructor();

        expect($importer->getJobConnection())->toBe('database-berat')->and($importer->getJobQueue())->toBe('berat');
    }
});

test('lock aksi memakai store database secara bawaan', function () {
    expect(file_get_contents(config_path('keuangan.php')))->toContain("env('KEUANGAN_LOCK_STORE', 'database')");
});

test('job masuk tabel jobs dan diproses worker pada koneksi database dan database-berat', function () {
    foreach ([['database', 'default'], ['database-berat', 'berat']] as [$koneksi, $antrean]) {
        $kunci = "uji-antrean-{$antrean}";

        dispatch(fn () => cache()->put($kunci, 'selesai', 60))->onConnection($koneksi)->onQueue($antrean);

        expect(DB::table('jobs')->where('queue', $antrean)->count())->toBe(1);

        $this->artisan("queue:work {$koneksi} --queue={$antrean} --stop-when-empty --memory=512")->assertSuccessful();

        expect(cache()->get($kunci))->toBe('selesai')
            ->and(DB::table('jobs')->where('queue', $antrean)->count())->toBe(0);
    }
});
