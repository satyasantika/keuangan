<?php

use App\Enums\Peran;
use App\Exceptions\KesalahanDomain;
use App\Filament\Pages\Pengaturan as HalamanPengaturan;
use App\Filament\Resources\JenisPajaks\Pages\EditJenisPajak;
use App\Filament\Resources\JenisPajaks\RelationManagers\TarifPajakRelationManager;
use App\Models\JenisPajak;
use App\Models\JenisPencairan;
use App\Models\TarifPajak;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Database\Seeders\JenisPajakSeeder;
use Database\Seeders\JenisPencairanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\TarifPajakContohSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed([PengaturanSeeder::class, JenisPencairanSeeder::class, JenisPajakSeeder::class]);
    $this->pph23 = JenisPajak::where('kode', 'pph23')->firstOrFail();
});

test('TarifPajak::berlakuPada memilih tarif yang benar di sekitar tanggal pergantian', function () {
    $lama = TarifPajak::create(['jenis_pajak_id' => $this->pph23->id, 'uraian' => 'Jasa', 'tarif_persen' => '1.00', 'berlaku_mulai' => '2026-01-01', 'berlaku_sampai' => '2026-12-31']);
    $baru = TarifPajak::create(['jenis_pajak_id' => $this->pph23->id, 'uraian' => 'Jasa', 'tarif_persen' => '3.00', 'berlaku_mulai' => '2027-01-01']);

    expect(TarifPajak::berlakuPada($this->pph23, Carbon::parse('2026-12-31'))->id)->toBe($lama->id)
        ->and(TarifPajak::berlakuPada($this->pph23, Carbon::parse('2027-01-01'))->id)->toBe($baru->id)
        ->and(TarifPajak::berlakuPada($this->pph23, Carbon::parse('2025-06-01')))->toBeNull();

    $baru->update(['aktif' => false]);
    expect(TarifPajak::berlakuPada($this->pph23, Carbon::parse('2027-02-01')))->toBeNull();
});

test('rentang tarif beruraian sama yang tumpang tindih ditolak', function () {
    TarifPajak::create(['jenis_pajak_id' => $this->pph23->id, 'uraian' => 'Jasa', 'tarif_persen' => '2.00', 'berlaku_mulai' => '2026-01-01']);

    expect(fn () => TarifPajak::create(['jenis_pajak_id' => $this->pph23->id, 'uraian' => 'Jasa', 'tarif_persen' => '3.00', 'berlaku_mulai' => '2027-01-01']))
        ->toThrow(KesalahanDomain::class, 'tumpang tindih');

    // Uraian berbeda boleh.
    TarifPajak::create(['jenis_pajak_id' => $this->pph23->id, 'uraian' => 'Sewa', 'tarif_persen' => '2.00', 'berlaku_mulai' => '2026-01-01']);
    expect(TarifPajak::count())->toBe(2);
});

test('relation manager menolak tarif tumpang tindih lewat validasi form', function () {
    TarifPajak::create(['jenis_pajak_id' => $this->pph23->id, 'uraian' => 'Jasa', 'tarif_persen' => '2.00', 'berlaku_mulai' => '2026-01-01', 'berlaku_sampai' => '2026-12-31']);

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminKeuangan))
        ->test(TarifPajakRelationManager::class, ['ownerRecord' => $this->pph23, 'pageClass' => EditJenisPajak::class])
        ->callTableAction('create', data: ['uraian' => 'Jasa', 'tarif_persen' => '2', 'berlaku_mulai' => '2026-06-01', 'berlaku_sampai' => '2027-06-01'])
        ->assertHasTableActionErrors(['berlaku_sampai']);

    expect(TarifPajak::count())->toBe(1);
});

test('Pengaturan::get mengembalikan tipe sesuai kolom dan set menghapus cache', function () {
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);

    expect(Pengaturan::get('batas_hari_spj'))->toBe(14)
        ->and(Pengaturan::get('wajib_persetujuan_wadek'))->toBeTrue()
        ->and(Pengaturan::get('honor_mengurangi_pagu'))->toBeFalse()
        ->and(Pengaturan::get('format_nomor_pengajuan'))->toBe('PGJ/{tahun}/{urut}')
        ->and(Pengaturan::get('tidak_ada', 'x'))->toBe('x')
        ->and(Cache::has(Pengaturan::KUNCI_CACHE))->toBeTrue();

    Pengaturan::set('batas_hari_spj', 30, $keu);

    expect(Cache::has(Pengaturan::KUNCI_CACHE))->toBeFalse()
        ->and(Pengaturan::get('batas_hari_spj'))->toBe(30)
        ->and(Activity::where('log_name', 'pengaturan')->where('causer_id', $keu->id)->exists())->toBeTrue();
});

test('halaman pengaturan: format nomor tanpa {urut} ditolak, nilai sah tersimpan', function () {
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);

    Livewire::actingAs($keu)->test(HalamanPengaturan::class)
        ->assertSet('data.batas_hari_spj', 14)
        ->fillForm(['format_nomor_pengajuan' => 'PGJ/{tahun}'])
        ->call('save')
        ->assertHasFormErrors(['format_nomor_pengajuan']);

    Livewire::actingAs($keu)->test(HalamanPengaturan::class)
        ->fillForm(['batas_hari_spj' => 91])
        ->call('save')
        ->assertHasFormErrors(['batas_hari_spj']);

    Livewire::actingAs($keu)->test(HalamanPengaturan::class)
        ->fillForm(['batas_hari_spj' => 30, 'wajib_persetujuan_wadek' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Pengaturan::get('batas_hari_spj'))->toBe(30)
        ->and(Pengaturan::get('wajib_persetujuan_wadek'))->toBeFalse();
});

test('admin-unit tidak bisa membuka/menyimpan pengaturan; bpp hanya melihat', function () {
    $this->actingAs(penggunaDenganPeran(Peran::AdminUnit))->get('/panel/pengaturan')->assertForbidden();

    $bpp = penggunaDenganPeran(Peran::Bpp);
    $this->actingAs($bpp)->get('/panel/pengaturan')->assertOk();

    Livewire::actingAs($bpp)->test(HalamanPengaturan::class)
        ->call('save')
        ->assertForbidden();

    expect(Pengaturan::get('batas_hari_spj'))->toBe(14);
});

test('seeder jenis pencairan & tarif contoh ditandai perlu verifikasi', function () {
    $this->seed(TarifPajakContohSeeder::class);

    expect(JenisPencairan::pluck('kode')->sort()->values()->all())->toBe(['LS', 'TUP', 'UP'])
        ->and(JenisPencairan::where('perlu_verifikasi', false)->exists())->toBeFalse()
        ->and(TarifPajak::count())->toBeGreaterThan(0)
        ->and(TarifPajak::where('perlu_verifikasi', false)->exists())->toBeFalse()
        ->and(TarifPajak::whereNotNull('dasar_hukum')->exists())->toBeFalse();

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminKeuangan))
        ->test(TarifPajakRelationManager::class, ['ownerRecord' => $this->pph23, 'pageClass' => EditJenisPajak::class])
        ->assertSee('Perlu verifikasi');
});
