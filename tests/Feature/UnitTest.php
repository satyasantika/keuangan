<?php

use App\Concerns\DibatasiUnit;
use App\Enums\Peran;
use App\Filament\Imports\UnitImporter;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\UnitContohSeeder;
use Filament\Actions\ImportAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(UnitContohSeeder::class);
    $this->pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $this->pbio = Unit::where('kode', 'PBIO')->firstOrFail();
});

test('scope terlihatOleh: admin-unit PMAT hanya melihat pengguna PMAT', function () {
    $adminPmat = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]);
    User::factory()->create(['unit_id' => $this->pbio->id]);
    User::factory()->create(['unit_id' => $this->pmat->id]);

    // User belum memakai DibatasiUnit; uji trait lewat model anonim bertabel users.
    $model = new class extends User
    {
        use DibatasiUnit;
    };

    $hasil = $model->newQuery()->terlihatOleh($adminPmat)->pluck('unit_id')->unique()->values()->all();

    expect($hasil)->toBe([$this->pmat->id]);
});

test('scope terlihatOleh: kepala-unit dibatasi, pimpinan melihat semua, tanpa unit tidak melihat apa pun', function () {
    $model = new class extends User
    {
        use DibatasiUnit;
    };
    User::factory()->create(['unit_id' => $this->pbio->id]);
    User::factory()->create(['unit_id' => $this->pmat->id]);

    $kepala = penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $this->pbio->id]);
    $pimpinan = penggunaDenganPeran(Peran::Pimpinan);
    $tanpaUnit = penggunaDenganPeran(Peran::AdminUnit);

    expect($model->newQuery()->terlihatOleh($kepala)->pluck('unit_id')->unique()->values()->all())->toBe([$this->pbio->id])
        ->and($model->newQuery()->terlihatOleh($pimpinan)->whereNotNull('unit_id')->pluck('unit_id')->unique()->count())->toBe(2)
        ->and($model->newQuery()->terlihatOleh($tanpaUnit)->count())->toBe(0)
        ->and($pimpinan->lingkupFakultas())->toBeTrue()
        ->and($kepala->lingkupFakultas())->toBeFalse();
});

test('pengguna admin-unit tanpa unit ditolak validasi', function () {
    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    $idAdminUnit = Role::where('name', Peran::AdminUnit->value)->value('id');

    Livewire::actingAs($admin)->test(CreateUser::class)
        ->fillForm([
            'name' => 'Admin PMAT',
            'email' => 'adminpmat@unsil.ac.id',
            'password' => 'kata-sandi-aman-1',
            'roles' => [$idAdminUnit],
        ])
        ->call('create')
        ->assertHasFormErrors(['unit_id']);

    Livewire::actingAs($admin)->test(CreateUser::class)
        ->fillForm([
            'name' => 'Admin PMAT',
            'email' => 'adminpmat@unsil.ac.id',
            'password' => 'kata-sandi-aman-1',
            'roles' => [$idAdminUnit],
            'unit_id' => $this->pmat->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'adminpmat@unsil.ac.id')->value('unit_id'))->toBe($this->pmat->id);
});

test('cache unit aktif terhapus setelah unit diubah', function () {
    expect(Unit::aktifTercache())->toHaveCount(5);
    expect(Cache::has(Unit::KUNCI_CACHE_AKTIF))->toBeTrue();

    $this->pbio->update(['aktif' => false]);

    expect(Cache::has(Unit::KUNCI_CACHE_AKTIF))->toBeFalse()
        ->and(Unit::aktifTercache())->toHaveCount(4);

    $this->pmat->delete();
    expect(Cache::has(Unit::KUNCI_CACHE_AKTIF))->toBeFalse();
});

test('impor unit membuat baru dan memperbarui berdasarkan kode', function () {
    $this->actingAs(penggunaDenganPeran(Peran::AdminKeuangan));

    UnitImporter::test()->import([
        'kode' => 'pmat',
        'nama' => 'Pendidikan Matematika (resmi)',
        'jenis' => 'prodi',
        'jenjang' => 'S1',
    ])->assertImported();

    UnitImporter::test()->import([
        'kode' => 'PFIS',
        'nama' => 'Pendidikan Fisika',
        'jenis' => '',
        'jenjang' => 'S1',
    ])->assertImported();

    UnitImporter::test()->import([
        'kode' => 'PX',
        'nama' => 'Tidak sah',
        'jenis' => 'fakultas',
        'jenjang' => '',
    ])->assertHasErrors(['jenis']);

    expect(Unit::where('kode', 'PMAT')->count())->toBe(1)
        ->and($this->pmat->fresh()->nama)->toBe('Pendidikan Matematika (resmi)')
        ->and(Unit::where('kode', 'PFIS')->value('nama'))->toBe('Pendidikan Fisika')
        ->and(Unit::where('kode', 'PX')->exists())->toBeFalse();
});

test('admin-keuangan membuat unit; admin-unit hanya melihat', function () {
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);

    Livewire::actingAs($keu)->test(CreateUnit::class)
        ->fillForm(['kode' => 'pkim', 'nama' => 'Pendidikan Kimia', 'jenis' => 'prodi'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Unit::where('kode', 'PKIM')->exists())->toBeTrue();

    $unit = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]);
    $this->actingAs($unit)->get('/panel/unit')->assertOk();
    $this->actingAs($unit)->get('/panel/unit/create')->assertForbidden();
});

test('aksi impor unit tersedia untuk admin-keuangan dan tersembunyi bagi admin-unit', function () {
    $imports = ImportAction::fake();

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminKeuangan))
        ->test(ListUnits::class)
        ->mountAction('import')
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('unit.csv', "kode,nama,jenis,jenjang\nPFIS,Pendidikan Fisika,prodi,S1\n")])
        ->fillForm(['columnMap' => ['kode' => 'kode', 'nama' => 'nama', 'jenis' => 'jenis', 'jenjang' => 'jenjang']])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $imports->assertDispatchedTimes(UnitImporter::class);

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]))
        ->test(ListUnits::class)
        ->assertActionHidden('import');
});
