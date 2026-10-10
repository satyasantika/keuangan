<?php

use App\Actions\Master\BuatTahunAnggaran;
use App\Actions\Master\UbahStatusTahunAnggaran;
use App\Enums\Peran;
use App\Enums\StatusPeriode;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PeriodeTerkunciException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Filament\Resources\TahunAnggarans\Pages\CreateTahunAnggaran;
use App\Filament\Resources\TahunAnggarans\Pages\ListTahunAnggarans;
use App\Models\Periode;
use App\Models\RiwayatStatus;
use App\Models\TahunAnggaran;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\Support\ModelUji;

function buatTahun(int $tahun = 2027): TahunAnggaran
{
    return app(BuatTahunAnggaran::class)->handle($tahun, penggunaDenganPeran(Peran::AdminKeuangan));
}

test('membuat tahun 2027 menghasilkan 12 periode terbuka dengan tanggal benar', function () {
    $tahun = buatTahun(2027);

    expect($tahun->status)->toBe(StatusTahunAnggaran::Perencanaan)
        ->and($tahun->nama)->toBe('Tahun Anggaran 2027')
        ->and($tahun->tanggal_mulai->toDateString())->toBe('2027-01-01')
        ->and($tahun->tanggal_selesai->toDateString())->toBe('2027-12-31');

    $periode = $tahun->periode()->get();
    expect($periode)->toHaveCount(12)
        ->and($periode->every(fn ($p) => $p->status === StatusPeriode::Terbuka))->toBeTrue()
        ->and($periode[0]->nama)->toBe('Januari 2027')
        ->and($periode[11]->nama)->toBe('Desember 2027')
        ->and($periode[1]->tanggal_selesai->toDateString())->toBe('2027-02-28');
});

test('tahun kabisat 2028 memiliki februari 29 hari', function () {
    $februari = buatTahun(2028)->periode()->where('bulan', 2)->first();

    expect($februari->tanggal_selesai->toDateString())->toBe('2028-02-29');
});

test('tahun ganda ditolak', function () {
    buatTahun(2027);

    expect(fn () => buatTahun(2027))->toThrow(KesalahanDomain::class, 'sudah ada');
    expect(TahunAnggaran::count())->toBe(1);
});

test('pembuatan mencatat riwayat status', function () {
    $tahun = buatTahun(2027);

    $riwayat = RiwayatStatus::where('model_id', $tahun->id)->get();
    expect($riwayat)->toHaveCount(1)
        ->and($riwayat[0]->model_type)->toBe('tahun_anggaran')
        ->and($riwayat[0]->dari)->toBeNull()
        ->and($riwayat[0]->ke)->toBe('perencanaan');
});

test('status bergerak satu arah dan mundur ditolak', function () {
    $tahun = buatTahun(2027);
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);
    $aksi = app(UbahStatusTahunAnggaran::class);

    $aksi->handle($tahun, StatusTahunAnggaran::Berjalan, $keu);
    expect($tahun->fresh()->status)->toBe(StatusTahunAnggaran::Berjalan);

    expect(fn () => $aksi->handle($tahun, StatusTahunAnggaran::Perencanaan, $keu))
        ->toThrow(TransisiStatusTidakSahException::class);

    $aksi->handle($tahun, StatusTahunAnggaran::Ditutup, $keu);

    expect(fn () => $aksi->handle($tahun, StatusTahunAnggaran::Berjalan, $keu))
        ->toThrow(TransisiStatusTidakSahException::class)
        ->and(RiwayatStatus::where('model_id', $tahun->id)->count())->toBe(3);
});

test('lompat status perencanaan langsung ke ditutup ditolak', function () {
    $tahun = buatTahun(2027);

    expect(fn () => app(UbahStatusTahunAnggaran::class)->handle($tahun, StatusTahunAnggaran::Ditutup, penggunaDenganPeran(Peran::AdminKeuangan)))
        ->toThrow(TransisiStatusTidakSahException::class);
});

test('admin-unit tidak bisa membuat atau mengubah status tahun anggaran', function () {
    $unit = penggunaDenganPeran(Peran::AdminUnit);

    expect(fn () => app(BuatTahunAnggaran::class)->handle(2027, $unit))->toThrow(AuthorizationException::class);

    $tahun = buatTahun(2027);
    expect(fn () => app(UbahStatusTahunAnggaran::class)->handle($tahun, StatusTahunAnggaran::Berjalan, $unit))
        ->toThrow(AuthorizationException::class);
});

test('periode untukTanggal menemukan periode atau melempar KesalahanDomain', function () {
    buatTahun(2027);

    expect(Periode::untukTanggal('2027-03-15')->nama)->toBe('Maret 2027')
        ->and(Periode::untukTanggal(Carbon\Carbon::parse('2027-12-31'))->nama)->toBe('Desember 2027')
        ->and(fn () => Periode::untukTanggal('2030-01-01'))->toThrow(KesalahanDomain::class);
});

test('simpan pada periode terkunci melempar PeriodeTerkunciException', function () {
    $tahun = buatTahun(2027);
    $maret = $tahun->periode()->where('bulan', 3)->first();
    $maret->forceFill(['status' => StatusPeriode::Terkunci])->save();

    expect(fn () => ModelUji::create(['tanggal' => '2027-03-10']))
        ->toThrow(PeriodeTerkunciException::class, 'Periode Maret 2027 terkunci.');

    $ok = ModelUji::create(['tanggal' => '2027-04-10']);
    expect($ok->periode_id)->toBe($tahun->periode()->where('bulan', 4)->value('id'));
});

test('memindahkan tanggal dari periode terkunci juga ditolak', function () {
    $tahun = buatTahun(2027);
    $model = ModelUji::create(['tanggal' => '2027-03-10']);
    $tahun->periode()->where('bulan', 3)->first()->forceFill(['status' => StatusPeriode::Terkunci])->save();

    expect(fn () => $model->update(['tanggal' => '2027-04-10']))->toThrow(PeriodeTerkunciException::class)
        ->and(fn () => $model->update(['catatan' => 'ubah']))->toThrow(PeriodeTerkunciException::class)
        ->and(fn () => $model->delete())->toThrow(PeriodeTerkunciException::class);
});

test('memindahkan tanggal KE periode terkunci ditolak', function () {
    $tahun = buatTahun(2027);
    $model = ModelUji::create(['tanggal' => '2027-04-10']);
    $tahun->periode()->where('bulan', 5)->first()->forceFill(['status' => StatusPeriode::Terkunci])->save();

    expect(fn () => $model->update(['tanggal' => '2027-05-10']))->toThrow(PeriodeTerkunciException::class);
});

test('tahun anggaran ditutup menolak penulisan walau periode terbuka', function () {
    $tahun = buatTahun(2027);
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);
    $aksi = app(UbahStatusTahunAnggaran::class);
    $aksi->handle($tahun, StatusTahunAnggaran::Berjalan, $keu);
    $aksi->handle($tahun, StatusTahunAnggaran::Ditutup, $keu);

    expect(fn () => ModelUji::create(['tanggal' => '2027-06-01']))->toThrow(KesalahanDomain::class, 'ditutup');
});

test('tanggal tanpa periode ditolak saat menyimpan', function () {
    expect(fn () => ModelUji::create(['tanggal' => '2040-01-01']))->toThrow(KesalahanDomain::class);
});

test('panel: admin-keuangan membuat tahun anggaran lewat form', function () {
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);

    Livewire::actingAs($keu)->test(CreateTahunAnggaran::class)
        ->fillForm(['tahun' => 2029])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TahunAnggaran::where('tahun', 2029)->first()->periode)->toHaveCount(12);
});

test('panel: admin-unit melihat daftar tetapi tidak ada tombol buat', function () {
    buatTahun(2027);
    $unit = penggunaDenganPeran(Peran::AdminUnit);

    $this->actingAs($unit)->get('/panel/tahun-anggaran')->assertOk();
    $this->actingAs($unit)->get('/panel/tahun-anggaran/create')->assertForbidden();

    Livewire::actingAs($unit)->test(ListTahunAnggarans::class)->assertActionHidden('create');
});

test('panel: tahun yang bukan perencanaan tidak boleh dihapus', function () {
    $tahun = buatTahun(2027);
    $keu = penggunaDenganPeran(Peran::AdminKeuangan);
    app(UbahStatusTahunAnggaran::class)->handle($tahun, StatusTahunAnggaran::Berjalan, $keu);

    expect($keu->can('delete', $tahun->fresh()))->toBeFalse();
});
