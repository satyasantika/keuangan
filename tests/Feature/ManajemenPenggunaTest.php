<?php

use App\Enums\Peran;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

test('super-admin dapat membuka daftar pengguna dan membuat pengguna', function () {
    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    $this->actingAs($admin)->get('/panel/users')->assertOk();

    $peranId = Role::where('name', Peran::Pimpinan->value)->value('id');

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'Dekan Uji',
            'email' => 'dekan@unsil.ac.id',
            'password' => 'kata-sandi-aman-1',
            'aktif' => true,
            'roles' => [$peranId],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $baru = User::where('email', 'dekan@unsil.ac.id')->firstOrFail();
    expect($baru->hasRole('pimpinan'))->toBeTrue()
        ->and($baru->password)->not->toBe('kata-sandi-aman-1');
});

test('admin-keuangan dapat melihat daftar tetapi tidak dapat membuat', function () {
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);

    $this->actingAs($keu)->get('/panel/users')->assertOk();
    $this->actingAs($keu)->get('/panel/users/create')->assertForbidden();
});

test('admin-unit mendapat 403 pada /panel/users', function () {
    $this->actingAs(penggunaDenganPeran(Peran::AdminUnit))->get('/panel/users')->assertForbidden();
});

test('email non-unsil ditolak di production', function () {
    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    app()['env'] = 'production';

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm(['name' => 'X', 'email' => 'x@gmail.com', 'password' => 'kata-sandi-aman-1'])
        ->call('create')
        ->assertHasFormErrors(['email']);

    expect(User::where('email', 'x@gmail.com')->exists())->toBeFalse();
});

test('hanya super-admin yang boleh memberi peran super-admin', function () {
    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    $idSuper = Role::where('name', 'super-admin')->value('id');

    // Peran dengan izin pengguna.buat selain super-admin tidak ada di matriks; beri izin langsung.
    $lain = penggunaDenganPeran(Peran::AdminKeuangan);
    $lain->givePermissionTo('pengguna.buat');

    Livewire::actingAs($lain)
        ->test(CreateUser::class)
        ->fillForm(['name' => 'Y', 'email' => 'y@unsil.ac.id', 'password' => 'kata-sandi-aman-1', 'roles' => [$idSuper]])
        ->call('create')
        ->assertHasFormErrors(['roles']);

    expect($admin->can('delete', User::factory()->create()))->toBeTrue();
});

test('pengguna tidak dapat menghapus dirinya sendiri', function () {
    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    $lain = User::factory()->create();

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('delete', $lain))->toBeTrue();
});

test('daftar pengguna menampilkan peran sebagai lencana dan dapat difilter', function () {
    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    $unit = penggunaDenganPeran(Peran::AdminUnit, ['name' => 'Admin Unit Uji']);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords(User::all())
        ->filterTable('peran', Peran::AdminUnit->value)
        ->assertCanSeeTableRecords([$unit])
        ->assertCanNotSeeTableRecords([$admin]);
});

test('horizon hanya dapat dibuka super-admin', function () {
    $this->actingAs(penggunaDenganPeran(Peran::AdminKeuangan))->get('/horizon')->assertForbidden();
    $this->get('/horizon')->assertForbidden();
    $this->actingAs(penggunaDenganPeran(Peran::SuperAdmin))->get('/horizon')->assertOk();
});
