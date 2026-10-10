<?php

use App\Actions\Master\TetapkanPagu;
use App\Actions\Master\UbahPaguDitetapkan;
use App\Actions\Master\UbahStatusTahunAnggaran;
use App\Enums\Peran;
use App\Enums\StatusPagu;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Filament\Resources\Pagus\Pages\CreatePagu;
use App\Filament\Resources\Pagus\Pages\ListPagus;
use App\Filament\Resources\Pagus\Pages\ViewPagu;
use App\Filament\Resources\Pagus\RelationManagers\PaguAkunRelationManager;
use App\Models\Akun;
use App\Models\Pagu;
use App\Models\PaguAkun;
use App\Models\RiwayatStatus;
use App\Models\Unit;
use Database\Seeders\UnitContohSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(UnitContohSeeder::class);
    $this->tahun = buatTahun(2027);
    $this->pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $this->pbio = Unit::where('kode', 'PBIO')->firstOrFail();
    $this->keu = penggunaDenganPeran(Peran::AdminKeuangan);
    $this->wadek = penggunaDenganPeran(Peran::WadekKeuangan);
    $this->pagu = Pagu::factory()->create([
        'tahun_anggaran_id' => $this->tahun->id,
        'unit_id' => $this->pmat->id,
        'jumlah' => '150000000.00',
        'dibuat_oleh' => $this->keu->id,
    ]);
});

test('admin-keuangan membuat pagu lewat panel; pasangan tahun-unit ganda ditolak', function () {
    Livewire::actingAs($this->keu)->test(CreatePagu::class)
        ->fillForm(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => $this->pbio->id, 'jumlah' => '50000000'])
        ->call('create')
        ->assertHasNoFormErrors();

    $baru = Pagu::where('unit_id', $this->pbio->id)->firstOrFail();
    expect($baru->status)->toBe(StatusPagu::Draf)
        ->and($baru->jumlah)->toBe('50000000.00')
        ->and($baru->dibuat_oleh)->toBe($this->keu->id);

    Livewire::actingAs($this->keu)->test(CreatePagu::class)
        ->fillForm(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => $this->pmat->id, 'jumlah' => '1'])
        ->call('create')
        ->assertHasFormErrors(['unit_id']);

    Livewire::actingAs($this->keu)->test(CreatePagu::class)
        ->fillForm(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => Unit::where('kode', 'PBI')->value('id'), 'jumlah' => '-5'])
        ->call('create')
        ->assertHasFormErrors(['jumlah']);
});

test('Σ rincian akun melebihi pagu ditolak (model dan form)', function () {
    [$a1, $a2] = Akun::factory()->count(2)->create();

    PaguAkun::create(['pagu_id' => $this->pagu->id, 'akun_id' => $a1->id, 'jumlah' => '100000000.00']);

    expect(fn () => PaguAkun::create(['pagu_id' => $this->pagu->id, 'akun_id' => $a2->id, 'jumlah' => '50000000.01']))
        ->toThrow(KesalahanDomain::class, 'melebihi pagu');

    Livewire::actingAs($this->keu)
        ->test(PaguAkunRelationManager::class, ['ownerRecord' => $this->pagu, 'pageClass' => ViewPagu::class])
        ->callTableAction('create', data: ['akun_id' => $a2->id, 'jumlah' => '60000000'])
        ->assertHasTableActionErrors(['jumlah']);

    PaguAkun::create(['pagu_id' => $this->pagu->id, 'akun_id' => $a2->id, 'jumlah' => '50000000.00']);
    expect($this->pagu->totalRincianAkun())->toBe('150000000.00');

    // Pagu tidak boleh diturunkan di bawah Σ rincian.
    expect(fn () => $this->pagu->update(['jumlah' => '149999999.99']))->toThrow(KesalahanDomain::class);
});

test('admin-keuangan tidak bisa menetapkan; wadek bisa dan riwayat tercatat', function () {
    expect(fn () => app(TetapkanPagu::class)->handle($this->pagu, $this->keu))->toThrow(AuthorizationException::class);

    Livewire::actingAs($this->keu)->test(ListPagus::class)->assertTableActionHidden('tetapkan', $this->pagu);

    Livewire::actingAs($this->wadek)->test(ListPagus::class)
        ->callTableAction('tetapkan', $this->pagu);

    $pagu = $this->pagu->fresh();
    expect($pagu->status)->toBe(StatusPagu::Ditetapkan)
        ->and($pagu->ditetapkan_oleh)->toBe($this->wadek->id)
        ->and($pagu->ditetapkan_pada)->not->toBeNull();

    $riwayat = RiwayatStatus::where('model_type', 'pagu')->where('model_id', $pagu->id)->get();
    expect($riwayat)->toHaveCount(1)
        ->and($riwayat[0]->dari)->toBe('draf')
        ->and($riwayat[0]->ke)->toBe('ditetapkan')
        ->and($riwayat[0]->user_id)->toBe($this->wadek->id);

    expect(fn () => app(TetapkanPagu::class)->handle($pagu, $this->wadek))->toThrow(TransisiStatusTidakSahException::class);
});

test('draf tidak tampil sebagai berlaku', function () {
    expect(Pagu::berlakuUntuk($this->tahun->id, $this->pmat->id))->toBeNull()
        ->and($this->pagu->berlaku())->toBeFalse();

    app(TetapkanPagu::class)->handle($this->pagu, $this->wadek);

    expect(Pagu::berlakuUntuk($this->tahun->id, $this->pmat->id)?->id)->toBe($this->pagu->id);
});

test('admin-unit PMAT tidak bisa melihat pagu PBIO (403 lewat URL id) dan daftar hanya unitnya', function () {
    $paguPbio = Pagu::factory()->create(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => $this->pbio->id, 'dibuat_oleh' => $this->keu->id]);
    $adminPmat = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]);

    $this->actingAs($adminPmat)->get("/panel/pagu/{$paguPbio->id}")->assertForbidden();
    $this->actingAs($adminPmat)->get("/panel/pagu/{$this->pagu->id}")->assertOk();

    Livewire::actingAs($adminPmat)->test(ListPagus::class)
        ->assertCanSeeTableRecords([$this->pagu])
        ->assertCanNotSeeTableRecords([$paguPbio])
        ->assertActionHidden('create');
});

test('pagu ditetapkan tidak bisa dihapus atau diedit; draf bisa dihapus', function () {
    expect($this->keu->can('delete', $this->pagu))->toBeTrue();

    app(TetapkanPagu::class)->handle($this->pagu, $this->wadek);
    $pagu = $this->pagu->fresh();

    expect($this->keu->can('delete', $pagu))->toBeFalse()
        ->and($this->keu->can('update', $pagu))->toBeFalse();

    $this->actingAs($this->keu)->get("/panel/pagu/{$pagu->id}/edit")->assertForbidden();
});

test('ubah pagu ditetapkan wajib alasan dan tercatat di riwayat', function () {
    app(TetapkanPagu::class)->handle($this->pagu, $this->wadek);
    $aksi = app(UbahPaguDitetapkan::class);

    expect(fn () => $aksi->handle($this->pagu, '200000000', '  ', $this->wadek))->toThrow(KesalahanDomain::class, 'Alasan')
        ->and(fn () => $aksi->handle($this->pagu, '200000000', 'Tambahan dana hibah', $this->keu))->toThrow(AuthorizationException::class);

    $aksi->handle($this->pagu, '200000000', 'Tambahan dana hibah', $this->wadek);

    expect($this->pagu->fresh()->jumlah)->toBe('200000000.00');

    $terakhir = RiwayatStatus::where('model_id', $this->pagu->id)->latest('id')->first();
    expect($terakhir->dari)->toBe('ditetapkan')
        ->and($terakhir->ke)->toBe('ditetapkan')
        ->and($terakhir->catatan)->toBe('Perubahan pagu dari Rp150.000.000,00 menjadi Rp200.000.000,00: Tambahan dana hibah');
});

test('pagu pada tahun anggaran ditutup tidak dapat disimpan', function () {
    $aksi = app(UbahStatusTahunAnggaran::class);
    $aksi->handle($this->tahun, StatusTahunAnggaran::Berjalan, $this->keu);
    tutupTahunPaksa($this->tahun);

    expect(fn () => $this->pagu->update(['keterangan' => 'ubah']))->toThrow(KesalahanDomain::class, 'ditutup');
});
