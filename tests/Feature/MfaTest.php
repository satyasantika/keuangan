<?php

use App\Enums\Peran;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Aktivitas;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('admin-keuangan tanpa mfa diarahkan ke pengaturan mfa', function (Peran $peran) {
    $user = penggunaDenganPeran($peran, mfa: false);

    $respons = $this->actingAs($user)->get('/panel');

    $respons->assertRedirect();
    expect($respons->headers->get('Location'))->toContain('multi-factor-authentication/set-up');
})->with([Peran::SuperAdmin, Peran::AdminKeuangan, Peran::Bpp, Peran::WadekKeuangan]);

test('halaman pengaturan mfa dapat dibuka tanpa putaran pengalihan', function () {
    $user = penggunaDenganPeran(Peran::AdminKeuangan, mfa: false);

    $this->actingAs($user)->get('/panel/multi-factor-authentication/set-up')->assertOk();
});

test('admin-unit dan pimpinan tanpa mfa tetap bisa masuk', function (Peran $peran) {
    $this->actingAs(penggunaDenganPeran($peran, mfa: false))->get('/panel')->assertOk();
})->with([Peran::AdminUnit, Peran::KepalaUnit, Peran::Pimpinan]);

test('admin-keuangan yang sudah mengaktifkan mfa dapat membuka panel', function () {
    $user = penggunaDenganPeran(Peran::AdminKeuangan);

    $this->actingAs($user)->get('/panel')->assertOk();
});

test('secret mfa tersimpan terenkripsi dan tidak masuk activitylog', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['kode-satu', 'kode-dua']);

    $mentah = DB::table('users')->where('id', $user->id)->first();

    expect($mentah->app_authentication_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($mentah->app_authentication_recovery_codes)->not->toContain('kode-satu')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['kode-satu', 'kode-dua']);

    $log = Aktivitas::where('subject_id', $user->id)->get()->map(fn ($a) => json_encode($a->attribute_changes).json_encode($a->properties))->implode('');
    expect($log)->not->toContain('JBSWY3DPEHPK3PXP')->not->toContain('app_authentication');
});

test('percobaan login ke-6 dalam semenit ditolak', function () {
    $user = User::factory()->create(['email' => 'uji@unsil.ac.id', 'password' => 'benar-benar-rahasia']);

    for ($i = 1; $i <= 5; $i++) {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'uji@unsil.ac.id', 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    // Percobaan ke-6 dengan kata sandi BENAR tetap ditolak.
    Livewire::test(Login::class)
        ->fillForm(['email' => 'uji@unsil.ac.id', 'password' => 'benar-benar-rahasia'])
        ->call('authenticate')
        ->assertNotified('Terlalu banyak percobaan masuk');

    $this->assertGuest();
    RateLimiter::clear('login-panel:'.hash('sha256', 'uji@unsil.ac.id|127.0.0.1'));
});

test('pembatasan login terpisah per surel', function () {
    User::factory()->create(['email' => 'b@unsil.ac.id', 'password' => 'rahasia-b-12345']);

    for ($i = 1; $i <= 5; $i++) {
        Livewire::test(Login::class)->fillForm(['email' => 'a@unsil.ac.id', 'password' => 'x'])->call('authenticate');
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => 'b@unsil.ac.id', 'password' => 'rahasia-b-12345'])
        ->call('authenticate')
        ->assertHasNoFormErrors();
});

test('reset mfa hanya oleh super-admin dan tercatat', function () {
    $target = User::factory()->create();
    $target->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $target->saveAppAuthenticationRecoveryCodes(['a', 'b']);

    $keu = penggunaDenganPeran(Peran::AdminKeuangan);

    Livewire::actingAs($keu)->test(ListUsers::class)
        ->assertActionHidden(TestAction::make('resetMfa')->table($target));

    $super = penggunaDenganPeran(Peran::SuperAdmin);

    Livewire::actingAs($super)->test(ListUsers::class)
        ->callAction(TestAction::make('resetMfa')->table($target));

    expect($target->fresh())
        ->app_authentication_secret->toBeNull()
        ->app_authentication_recovery_codes->toBeNull()
        ->and(Aktivitas::where('subject_id', $target->id)->where('event', 'reset-mfa')->count())->toBe(1);
});
