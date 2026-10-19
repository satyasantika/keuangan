<?php

use App\Enums\Peran;
use App\Filament\Resources\Akuns\Pages\ListAkuns;
use App\Filament\Resources\KategoriPenggunaanDanas\Pages\ManageKategoriPenggunaanDanas;
use App\Models\Akun;
use App\Models\BarisImporGagal;
use App\Models\Impor;
use App\Models\KategoriPenggunaanDana;
use Database\Seeders\AkunContohSeeder;
use Database\Seeders\KategoriPenggunaanDanaContohSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('impor csv 3 baris: 1 dibuat, 1 diperbarui, 1 gagal tercatat', function () {
    // importer diarahkan ke koneksi database-berat; tes menjalankannya inline
    config(['queue.connections.database-berat' => ['driver' => 'sync']]);
    Akun::factory()->create(['kode' => '521211', 'nama' => 'Nama lama', 'kelompok' => null, 'sumber' => 'contoh']);
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);

    $csv = "kode,nama,kelompok,jenis_belanja\n"
        ."524111,Belanja Perjalanan Dinas Biasa,,barang\n"
        ."521211,Belanja Bahan,,\n"
        ."52A1,Kode salah,,\n";

    Livewire::actingAs($keu)->test(ListAkuns::class)
        ->mountAction('import')
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)])
        ->fillForm(['columnMap' => ['kode' => 'kode', 'nama' => 'nama', 'kelompok' => 'kelompok', 'jenis_belanja' => 'jenis_belanja']])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $baru = Akun::where('kode', '524111')->first();
    $lama = Akun::where('kode', '521211')->first();

    expect(Akun::count())->toBe(2)
        ->and($baru->kelompok)->toBe('524')
        ->and($baru->sumber)->toBe('impor')
        ->and($baru->jenis_belanja)->toBe('barang')
        ->and($lama->nama)->toBe('Belanja Bahan')
        ->and($lama->sumber)->toBe('impor')
        ->and($lama->kelompok)->toBe('521');

    $impor = Impor::latest()->first();
    expect($impor->successful_rows)->toBe(2)
        ->and(BarisImporGagal::where('import_id', $impor->id)->count())->toBe(1)
        ->and(BarisImporGagal::first()->validation_error)->toContain('6 digit');
});

test('akun contoh berlencana CONTOH merah', function () {
    $this->seed(AkunContohSeeder::class);

    expect(Akun::where('sumber', 'contoh')->count())->toBe(6)
        ->and(Akun::first()->keterangan)->toContain('perlu verifikasi');

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminKeuangan))->test(ListAkuns::class)
        ->assertSee('CONTOH')
        ->assertTableColumnFormattedStateSet('sumber', 'CONTOH', Akun::first());
});

test('seeder contoh tidak menimpa akun hasil impor dan dilewati di produksi', function () {
    Akun::factory()->create(['kode' => '521211', 'nama' => 'Resmi', 'sumber' => 'impor']);
    $this->seed(AkunContohSeeder::class);
    expect(Akun::where('kode', '521211')->value('nama'))->toBe('Resmi');

    Akun::query()->where('sumber', 'contoh')->forceDelete();
    app()['env'] = 'production';
    app(AkunContohSeeder::class)->run();
    expect(Akun::where('sumber', 'contoh')->count())->toBe(0);
});

test('admin-unit hanya bisa melihat akun dan kategori', function () {
    $unit = penggunaDenganPeran(Peran::AdminUnit);

    $this->actingAs($unit)->get('/panel/akun')->assertOk();
    $this->actingAs($unit)->get('/panel/akun/create')->assertForbidden();

    Livewire::actingAs($unit)->test(ListAkuns::class)->assertActionHidden('import');
    Livewire::actingAs($unit)->test(ManageKategoriPenggunaanDanas::class)->assertActionHidden('create');
});

test('cache akun aktif dihapus setelah akun berubah', function () {
    $akun = Akun::factory()->create();
    expect(Akun::aktifTercache())->toHaveCount(1);

    $akun->update(['aktif' => false]);

    expect(Cache::has(Akun::KUNCI_CACHE_AKTIF))->toBeFalse()
        ->and(Akun::aktifTercache())->toHaveCount(0);
});

test('kategori contoh berurutan dan idempoten', function () {
    $this->seed(KategoriPenggunaanDanaContohSeeder::class);
    $this->seed(KategoriPenggunaanDanaContohSeeder::class);

    expect(KategoriPenggunaanDana::count())->toBe(7)
        ->and(KategoriPenggunaanDana::orderBy('urutan')->first()->nama)->toBe('Biaya operasional pendidikan');
});
