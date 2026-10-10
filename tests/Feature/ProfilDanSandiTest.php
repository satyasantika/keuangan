<?php

use App\Actions\Pengguna\AturWajibGantiSandi;
use App\Actions\Pengguna\ImporPenggunaTempel;
use App\Enums\Peran;
use App\Filament\Pages\Auth\EditProfil;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PenggunaAwalSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('pengguna memperbarui nama, NIP, dan no. HP di profil', function () {
    $user = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => Unit::factory()->create()->id]);
    $this->actingAs($user)->get('/panel/profile')->assertOk();

    Livewire::actingAs($user)->test(EditProfil::class)
        ->fillForm(['name' => 'Nama Baru', 'nip' => '1980', 'no_hp' => '0812'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())->name->toBe('Nama Baru')->nip->toBe('1980')->no_hp->toBe('0812');
});

test('ganti kata sandi butuh kata sandi sekarang, mencabut kewajiban ganti, dan memutus sesi lain', function () {
    $user = penggunaDenganPeran(Peran::Pimpinan, ['password' => 'sandi-lama-123', 'wajib_ganti_sandi' => true]);
    $hashLama = $user->password;
    $tokenLama = $user->getRememberToken();

    Livewire::actingAs($user)->test(EditProfil::class)
        ->fillForm(['password' => 'sandi-baru-456', 'passwordConfirmation' => 'sandi-baru-456', 'currentPassword' => 'salah'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    Livewire::actingAs($user)->test(EditProfil::class)
        ->fillForm(['password' => 'sandi-lama-123', 'passwordConfirmation' => 'sandi-lama-123', 'currentPassword' => 'sandi-lama-123'])
        ->call('save')
        ->assertHasFormErrors(['password']);

    Livewire::actingAs($user)->test(EditProfil::class)
        ->fillForm(['password' => 'sandi-baru-456', 'passwordConfirmation' => 'sandi-baru-456', 'currentPassword' => 'sandi-lama-123'])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect(Hash::check('sandi-baru-456', $user->password))->toBeTrue()
        ->and($user->wajib_ganti_sandi)->toBeFalse()
        ->and($user->sandi_diubah_pada)->not->toBeNull()
        ->and($user->getRememberToken())->not->toBe($tokenLama);

    // Sesi lain masih membawa hash sandi lama → AuthenticateSession menolaknya.
    $this->flushSession();
    $this->actingAs($user)->withSession(['password_hash_web' => $hashLama])->get('/panel')->assertRedirect(route('filament.keuangan.auth.login'));
});

test('kata sandi wajib diisi saat akun ditandai wajib ganti sandi', function () {
    $user = penggunaDenganPeran(Peran::Pimpinan, ['wajib_ganti_sandi' => true]);

    Livewire::actingAs($user)->test(EditProfil::class)->fillForm(['name' => 'X'])->call('save')->assertHasFormErrors(['password']);
});

test('akun wajib ganti sandi diarahkan ke profil dan bebas setelah mengganti', function () {
    $user = penggunaDenganPeran(Peran::Pimpinan, ['wajib_ganti_sandi' => true]);

    $this->actingAs($user)->get('/panel')->assertRedirect(route('filament.keuangan.auth.profile'));
    $this->actingAs($user)->get('/panel/laporan')->assertRedirect(route('filament.keuangan.auth.profile'));
    $this->actingAs($user)->get(route('filament.keuangan.auth.profile'))->assertOk();

    $user->forceFill(['wajib_ganti_sandi' => false])->save();
    $this->actingAs($user->fresh())->get('/panel')->assertOk();
});

test('hanya super-admin yang mengatur kewajiban ganti sandi, lewat aksi massal', function () {
    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    $sasaran = User::factory()->count(2)->create();

    Livewire::actingAs($admin)->test(ListUsers::class)
        ->selectTableRecords($sasaran->pluck('id')->all())
        ->callAction(TestAction::make('wajibkanGantiSandi')->table()->bulk());

    expect($sasaran->fresh()->every(fn (User $u) => $u->wajib_ganti_sandi))->toBeTrue();

    $keuangan = penggunaDenganPeran(Peran::AdminKeuangan);
    expect(fn () => app(AturWajibGantiSandi::class)->handle($sasaran, false, $keuangan))->toThrow(AuthorizationException::class);

    app(AturWajibGantiSandi::class)->handle($sasaran, false, $admin);
    expect($sasaran->fresh()->contains(fn (User $u) => $u->wajib_ganti_sandi))->toBeFalse();
});

describe('impor massal pengguna dari tempel', function () {
    beforeEach(function () {
        $this->admin = penggunaDenganPeran(Peran::SuperAdmin);
        $this->unit = Unit::factory()->create(['kode' => 'PMAT']);
    });

    test('tab, titik koma, dan koma diterima; judul dilewati; sandi awal dan kewajiban ganti terpasang', function () {
        $teks = "Nama\tSurel\tPeran\tUnit\tNIP\tHP\n"
            ."Budi Santoso\tbudi@unsil.ac.id\tadmin-unit\tPMAT\t1980\t0812\n"
            ."Siti;siti@unsil.ac.id;bpp\n"
            .'Rudi,rudi@unsil.ac.id,Pimpinan';

        $hasil = app(ImporPenggunaTempel::class)->handle($teks, 'sandi-awal-1', true, $this->admin);

        expect($hasil)->toBe(['dibuat' => 3, 'galat' => []]);

        $budi = User::where('email', 'budi@unsil.ac.id')->first();
        expect($budi->hasRole('admin-unit'))->toBeTrue()
            ->and($budi->unit_id)->toBe($this->unit->id)
            ->and($budi->nip)->toBe('1980')
            ->and($budi->wajib_ganti_sandi)->toBeTrue()
            ->and(Hash::check('sandi-awal-1', $budi->password))->toBeTrue()
            ->and(User::where('email', 'siti@unsil.ac.id')->first()->hasRole('bpp'))->toBeTrue();
    });

    test('satu baris salah membatalkan seluruh batch dan semua galat dilaporkan', function () {
        $teks = "Ok\tok@unsil.ac.id\tbpp\n"
            ."Tanpa Unit\ttu@unsil.ac.id\tadmin-unit\n"
            ."Peran Aneh\tpa@unsil.ac.id\tdukun\n"
            ."Unit Salah\tus@unsil.ac.id\tkepala-unit\tXXX\n"
            ."Dobel\tok@unsil.ac.id\tbpp\n"
            ."Surel Jelek\ttidakvalid\tbpp";

        $hasil = app(ImporPenggunaTempel::class)->handle($teks, 'sandi-awal-1', false, $this->admin);

        expect($hasil['dibuat'])->toBe(0)
            ->and($hasil['galat'])->toHaveCount(5)
            ->and(User::where('email', 'ok@unsil.ac.id')->exists())->toBeFalse();
    });

    test('surel yang sudah terdaftar ditolak', function () {
        User::factory()->create(['email' => 'ada@unsil.ac.id']);

        $hasil = app(ImporPenggunaTempel::class)->handle("A\tada@unsil.ac.id\tbpp", 'sandi-awal-1', false, $this->admin);

        expect($hasil['dibuat'])->toBe(0)->and($hasil['galat'][0])->toContain('sudah terdaftar');
    });

    test('admin-keuangan tidak boleh memberi peran super-admin dan kata sandi awal minimal 8 karakter', function () {
        $keuangan = penggunaDenganPeran(Peran::AdminKeuangan)->givePermissionTo('pengguna.buat');

        expect(app(ImporPenggunaTempel::class)->handle("A\ta@unsil.ac.id\tsuper-admin", 'sandi-awal-1', false, $keuangan)['galat'][0])->toContain('hanya super-admin')
            ->and(app(ImporPenggunaTempel::class)->handle("A\ta@unsil.ac.id\tbpp", 'pendek', false, $this->admin)['galat'])->toBe(['Kata sandi awal minimal 8 karakter.']);
    });

    test('peran tanpa izin menambah pengguna ditolak', function () {
        $adminUnit = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->unit->id]);

        expect(fn () => app(ImporPenggunaTempel::class)->handle("A\ta@unsil.ac.id\tbpp", 'sandi-awal-1', false, $adminUnit))->toThrow(AuthorizationException::class);
    });

    test('aksi impor di daftar pengguna membuat akun', function () {
        Livewire::actingAs($this->admin)->test(ListUsers::class)
            ->callAction(TestAction::make('imporTempel'), ['teks' => "Cici\tcici@unsil.ac.id\tpimpinan", 'kata_sandi' => 'sandi-awal-1', 'wajib_ganti_sandi' => true])
            ->assertNotified();

        expect(User::where('email', 'cici@unsil.ac.id')->exists())->toBeTrue();
    });
});

test('super-admin awal: fkip@unsil.ac.id / siliwangi, tanpa MFA, langsung bisa masuk panel', function () {
    $this->seed(PeranDanIzinSeeder::class);
    config(['keuangan.mfa_wajib' => false]);

    (new PenggunaAwalSeeder)->run();

    $admin = User::where('email', 'fkip@unsil.ac.id')->firstOrFail();

    expect(Hash::check('siliwangi', $admin->password))->toBeTrue()
        ->and($admin->hasRole('super-admin'))->toBeTrue()
        ->and($admin->wajibMfa())->toBeFalse()
        ->and($admin->wajib_ganti_sandi)->toBeFalse();

    $this->actingAs($admin)->get('/panel')->assertOk();
});

test('MFA wajib hanya berlaku bila keuangan.mfa_wajib aktif', function () {
    $user = penggunaDenganPeran(Peran::AdminKeuangan, mfa: false);

    config(['keuangan.mfa_wajib' => false]);
    expect($user->wajibMfa())->toBeFalse();
    $this->actingAs($user)->get('/panel')->assertOk();

    config(['keuangan.mfa_wajib' => true]);
    expect($user->wajibMfa())->toBeTrue();
});
