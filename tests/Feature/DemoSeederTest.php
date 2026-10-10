<?php

use App\Enums\StatusPengajuan;
use App\Models\Pagu;
use App\Models\Pengajuan;
use App\Models\Periode;
use App\Models\RekapPeriode;
use App\Models\TautanBerkas;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('DemoSeeder di lokal membuat pengguna per peran, pagu ditetapkan, dan 10 pengajuan', function () {
    $this->travelTo('2027-06-20 10:00:00');
    app()['env'] = 'local';
    config(['keuangan.lock_store' => 'array']);

    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class); // idempoten

    expect(User::where('email', 'like', '%@demo.test')->count())->toBe(10)
        ->and(User::where('email', 'admin-pmat@demo.test')->first()->hasRole('admin-unit'))->toBeTrue()
        ->and(Pagu::query()->berlaku()->count())->toBe(3)
        ->and(Pengajuan::count())->toBe(12)
        ->and(Pengajuan::where('status', StatusPengajuan::Draf)->count())->toBe(4)
        ->and(Pengajuan::pluck('status')->map->value->unique()->sort()->values()->all())->toBe([
            'diajukan', 'dicairkan', 'dikembalikan', 'disetujui', 'ditolak', 'diverifikasi_keuangan', 'diverifikasi_unit', 'draf', 'selesai',
        ])
        ->and(Pengajuan::where('status', 'dicairkan')->first()->batas_spj->isPast())->toBeTrue()
        ->and(TautanBerkas::where('pemilik_type', 'pengajuan')->count())->toBe(12);

    expect(Periode::where('status', 'terkunci')->pluck('bulan')->sort()->values()->all())->toBe([1, 2, 3, 4, 5])
        ->and(RekapPeriode::count())->toBeGreaterThan(0);

    $this->artisan('keuangan:rekonsiliasi')->assertSuccessful();
});

test('DemoSeeder tidak berjalan di luar lokal', function () {
    app(DemoSeeder::class)->run();

    expect(User::where('email', 'like', '%@demo.test')->count())->toBe(0);
});
