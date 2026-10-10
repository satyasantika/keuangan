<?php

use App\Actions\Pengguna\AkhiriImpersonasi;
use App\Actions\Pengguna\MulaiImpersonasi;
use App\Enums\Peran;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Aktivitas;
use App\Models\User;
use App\Support\Impersonasi;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    // Pembungkus HTTP agar Action berjalan dalam siklus sesi yang nyata.
    Route::middleware(['web', 'auth'])->post('/uji/impersonasi/{sasaran}', function (string $sasaran) {
        app(MulaiImpersonasi::class)->handle(auth()->user(), User::findOrFail($sasaran));

        return response('ok');
    });
    Route::middleware(['web', 'auth'])->get('/uji/siapa', fn () => response(auth()->id().'|'.(Impersonasi::idAsal() ?? '-')));
    Route::middleware(['web', 'auth'])->post('/uji/catat', function () {
        activity('uji')->causedBy(auth()->user())->log('tindakan');

        return response('ok');
    });

    $this->admin = penggunaDenganPeran(Peran::SuperAdmin);
    $this->sasaran = penggunaDenganPeran(Peran::Bpp, ['name' => 'Bendahara Uji']);
});

test('super-admin masuk sebagai pengguna lain, spanduk tampil, lalu kembali dengan audit lengkap', function () {
    $this->actingAs($this->admin)->post("/uji/impersonasi/{$this->sasaran->id}")->assertOk();

    $this->get('/uji/siapa')->assertSee("{$this->sasaran->id}|{$this->admin->id}");
    $this->get('/panel')->assertOk()->assertSee('Mode darurat')->assertSee('Bendahara Uji')->assertSee(route('impersonasi.berhenti'), false);

    $this->post(route('impersonasi.berhenti'))->assertRedirect();
    $this->get('/uji/siapa')->assertSee("{$this->admin->id}|-");
    $this->get('/panel')->assertDontSee('Mode darurat');

    $events = Aktivitas::where('log_name', 'users')->whereIn('event', ['impersonasi-mulai', 'impersonasi-akhir'])->orderBy('created_at')->get();
    expect($events->pluck('event')->all())->toBe(['impersonasi-mulai', 'impersonasi-akhir'])
        ->and($events->every(fn ($a) => $a->causer_id === $this->admin->id))->toBeTrue();
});

test('entri audit selama menyamar memuat id super-admin asal; di luar itu tidak', function () {
    $this->actingAs($this->admin)->post('/uji/catat');
    $this->post("/uji/impersonasi/{$this->sasaran->id}");
    $this->post('/uji/catat');
    $this->post(route('impersonasi.berhenti'));

    $uji = Aktivitas::where('log_name', 'uji')->orderBy('created_at')->get();

    expect($uji)->toHaveCount(2)
        ->and($uji[0]->properties->get('diimpersonasi_oleh'))->toBeNull()
        ->and($uji[1]->causer_id)->toBe($this->sasaran->id)
        ->and($uji[1]->properties->get('diimpersonasi_oleh'))->toBe($this->admin->id);
});

test('hanya super-admin; tidak untuk diri sendiri, akun nonaktif, atau saat sudah menyamar', function () {
    $keuangan = penggunaDenganPeran(Peran::AdminKeuangan)->givePermissionTo(['pengguna.lihat', 'pengguna.ubah']);

    $this->actingAs($keuangan)->post("/uji/impersonasi/{$this->sasaran->id}")->assertForbidden();

    expect(fn () => app(MulaiImpersonasi::class)->handle($keuangan, $this->sasaran))->toThrow(AuthorizationException::class);

    $this->actingAs($this->admin);
    expect(fn () => app(MulaiImpersonasi::class)->handle($this->admin, $this->admin))->toThrow(AuthorizationException::class);

    $nonaktif = User::factory()->create(['aktif' => false]);
    expect(fn () => app(MulaiImpersonasi::class)->handle($this->admin, $nonaktif))->toThrow(AuthorizationException::class);
});

test('tidak bisa menyamar bertingkat', function () {
    $lain = penggunaDenganPeran(Peran::Pimpinan);

    $this->actingAs($this->admin)->post("/uji/impersonasi/{$this->sasaran->id}")->assertOk();
    $this->post("/uji/impersonasi/{$lain->id}")->assertForbidden();
});

test('tombol Masuk sebagai hanya tampil untuk super-admin dan bukan untuk diri sendiri', function () {
    Livewire::actingAs($this->admin)->test(ListUsers::class)
        ->assertActionVisible(TestAction::make('impersonasi')->table($this->sasaran))
        ->assertActionHidden(TestAction::make('impersonasi')->table($this->admin));

    $keuangan = penggunaDenganPeran(Peran::AdminKeuangan)->givePermissionTo(['pengguna.lihat', 'pengguna.ubah']);
    Livewire::actingAs($keuangan)->test(ListUsers::class)
        ->assertActionHidden(TestAction::make('impersonasi')->table($this->sasaran));
});

test('selama menyamar profil tidak dapat dibuka dan kewajiban ganti sandi/MFA sasaran tidak mengganggu', function () {
    $this->sasaran->forceFill(['wajib_ganti_sandi' => true])->save();

    $this->actingAs($this->admin)->post("/uji/impersonasi/{$this->sasaran->id}")->assertOk();

    $this->get('/panel')->assertOk();
    $this->get(route('filament.keuangan.auth.profile'))->assertForbidden();
});

test('mengakhiri tanpa impersonasi ditolak', function () {
    $this->actingAs($this->admin);

    expect(fn () => app(AkhiriImpersonasi::class)->handle())->toThrow(AuthorizationException::class);
    $this->post(route('impersonasi.berhenti'))->assertForbidden();
});
