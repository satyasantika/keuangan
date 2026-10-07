<?php

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\TahapPersetujuan;
use App\Events\StatusBerubah;
use App\Models\Aktivitas;
use App\Models\RiwayatStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

enum StatusUji: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
}

test('riwayat status tersimpan lengkap', function () {
    $pembuat = User::factory()->create();
    $model = User::factory()->create();

    $riwayat = DB::transaction(fn () => app(CatatRiwayatStatus::class)->handle(
        $model, StatusUji::Draf, StatusUji::Diajukan, $pembuat, 'Mohon diproses', TahapPersetujuan::Keuangan,
    ));

    $tersimpan = RiwayatStatus::findOrFail($riwayat->id);

    expect($tersimpan)
        ->model_type->toBe('user')
        ->model_id->toBe($model->id)
        ->dari->toBe('draf')
        ->ke->toBe('diajukan')
        ->tahap->toBe(TahapPersetujuan::Keuangan)
        ->catatan->toBe('Mohon diproses')
        ->user_id->toBe($pembuat->id)
        ->and(Str::isUuid($tersimpan->id))->toBeTrue()
        ->and($tersimpan->created_at)->not->toBeNull();
});

test('status awal boleh tanpa status asal dan tanpa pengguna', function () {
    $model = User::factory()->create();

    $riwayat = DB::transaction(fn () => app(CatatRiwayatStatus::class)->handle($model, null, StatusUji::Draf, null));

    expect($riwayat->fresh())->dari->toBeNull()->user_id->toBeNull();
});

test('memanggil di luar transaksi melempar LogicException', function () {
    $model = User::factory()->create();

    // RefreshDatabase membungkus test dalam transaksi; keluar sementara agar level = 0.
    $level = DB::transactionLevel();
    for ($i = 0; $i < $level; $i++) {
        DB::rollBack();
    }

    try {
        expect(fn () => app(CatatRiwayatStatus::class)->handle($model, null, StatusUji::Draf, null))
            ->toThrow(LogicException::class);
    } finally {
        for ($i = 0; $i < $level; $i++) {
            DB::beginTransaction();
        }
    }
});

test('event StatusBerubah terkirim setelah commit', function () {
    $diterima = [];
    Event::listen(StatusBerubah::class, function (StatusBerubah $e) use (&$diterima) {
        $diterima[] = $e;
    });
    $model = User::factory()->create();

    DB::transaction(function () use ($model, &$diterima) {
        app(CatatRiwayatStatus::class)->handle($model, StatusUji::Draf, StatusUji::Diajukan, null);
        expect($diterima)->toBeEmpty();
    });

    expect($diterima)->toHaveCount(1)
        ->and($diterima[0]->ke)->toBe(StatusUji::Diajukan);
});

test('event StatusBerubah tidak terkirim bila transaksi di-rollback', function () {
    $diterima = [];
    Event::listen(StatusBerubah::class, function (StatusBerubah $e) use (&$diterima) {
        $diterima[] = $e;
    });
    $model = User::factory()->create();

    try {
        DB::transaction(function () use ($model) {
            app(CatatRiwayatStatus::class)->handle($model, StatusUji::Draf, StatusUji::Diajukan, null);
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
    }

    expect($diterima)->toBeEmpty()
        ->and(RiwayatStatus::count())->toBe(0);
});

test('activitylog user tidak memuat password', function () {
    $user = User::factory()->create(['name' => 'Nama Lama']);
    $user->update(['name' => 'Nama Baru', 'password' => 'kata-sandi-baru']);

    $log = Aktivitas::where('subject_id', $user->id)->where('event', 'updated')->latest()->firstOrFail();
    $json = json_encode($log->attribute_changes).json_encode($log->properties);

    expect($json)->toContain('Nama Baru')
        ->not->toContain('password')
        ->not->toContain('kata-sandi-baru')
        ->and($log->log_name)->toBe('users')
        ->and(Str::isUuid($log->id))->toBeTrue();
});
