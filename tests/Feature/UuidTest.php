<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('pengguna memakai uuid versi 7 sebagai id', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->id[14])->toBe('7');
});

test('kolom users.id bertipe uuid (MariaDB) atau char(36)', function () {
    $tipe = collect(Schema::getColumns('users'))->firstWhere('name', 'id');

    expect($tipe['type'])->toBeIn(['uuid', 'char(36)']);
});

test('route model binding dengan uuid berjalan', function () {
    Route::middleware('web')->get('/_uji/pengguna/{user}', fn (User $user) => $user->email)->whereUuid('user');

    $user = User::factory()->create();

    $this->get('/_uji/pengguna/'.$user->id)->assertOk()->assertSee($user->email);
    $this->get('/_uji/pengguna/'.Str::uuid7())->assertNotFound();
});
