<?php

use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\KembalikanSpj;
use App\Actions\Spj\TerimaSpj;
use App\Enums\Peran;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\Spjs\Pages\ViewSpj;
use App\Filament\Resources\Spjs\RelationManagers\TautanBekuRelationManager;
use App\Models\RiwayatStatus;
use App\Models\TautanBerkas;
use App\Models\TautanBerkasBeku;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->spj = buatSpjDenganRealisasi($this->k, '250000');
});

test('ajukan tanpa pernyataan berkas final → gagal', function () {
    expect(fn () => app(AjukanSpj::class)->handle($this->spj, $this->k->adminPmat, false))->toThrow(ValidationException::class);
});

test('pernyataan tersimpan dengan pengguna & waktu dan tercatat di riwayat', function () {
    $spj = app(AjukanSpj::class)->handle($this->spj, $this->k->adminPmat, true);

    expect($spj->pernyataan_final_oleh)->toBe($this->k->adminPmat->id)
        ->and($spj->pernyataan_final_pada)->not->toBeNull()
        ->and(RiwayatStatus::where('model_id', $spj->id)->value('catatan'))->toContain(AjukanSpj::PERNYATAAN_FINAL);
});

test('terima SPJ → snapshot = tautan realisasi + pencairan + TOR, merujuk riwayat penerimaan; tautan terkunci', function () {
    $spj = app(AjukanSpj::class)->handle($this->spj, $this->k->adminPmat, true);
    $spj = app(TerimaSpj::class)->handle($spj, $this->k->bpp);

    $riwayatTerima = RiwayatStatus::where('model_id', $spj->id)->where('ke', 'diterima')->firstOrFail();
    $beku = TautanBerkasBeku::where('spj_id', $spj->id)->get();

    // 1 kuitansi realisasi + 1 bukti pencairan + 1 TOR
    expect($beku)->toHaveCount(3)
        ->and($beku->pluck('riwayat_status_id')->unique()->all())->toBe([$riwayatTerima->id])
        ->and($beku->pluck('pemilik_type')->sort()->values()->all())->toBe(['pencairan', 'pengajuan', 'realisasi_item']);

    $tautanRealisasi = TautanBerkas::where('pemilik_type', 'realisasi_item')->firstOrFail();
    expect(fn () => $tautanRealisasi->update(['url' => 'https://drive.google.com/file/d/1Baru/view']))->toThrow(KesalahanDomain::class)
        ->and(fn () => $tautanRealisasi->fresh()->delete())->toThrow(KesalahanDomain::class);

    $tautanPencairan = TautanBerkas::where('pemilik_type', 'pencairan')->firstOrFail();
    expect(fn () => $tautanPencairan->update(['label' => 'Ubah']))->toThrow(KesalahanDomain::class);

    $satu = $beku->first();
    expect(fn () => $satu->update(['url' => 'x']))->toThrow(KesalahanDomain::class)
        ->and(fn () => $satu->delete())->toThrow(KesalahanDomain::class)
        ->and(TautanBekuRelationManager::berubah($satu->fresh()))->toBeFalse();
});

test('SPJ dikembalikan lalu diterima pada putaran kedua → snapshot hanya saat diterima', function () {
    $spj = app(AjukanSpj::class)->handle($this->spj, $this->k->adminPmat, true);
    $spj = app(KembalikanSpj::class)->handle($spj, $this->k->keu, 'Kuitansi kurang jelas');
    expect(TautanBerkasBeku::count())->toBe(0);

    $spj = app(AjukanSpj::class)->handle($spj, $this->k->adminPmat, true);
    app(TerimaSpj::class)->handle($spj, $this->k->keu);

    expect(TautanBerkasBeku::count())->toBe(3)
        ->and(TautanBerkasBeku::pluck('riwayat_status_id')->unique())->toHaveCount(1);
});

test('tab tautan dibekukan: pemilik melihat, unit lain tidak', function () {
    $spj = app(TerimaSpj::class)->handle(app(AjukanSpj::class)->handle($this->spj, $this->k->adminPmat, true), $this->k->bpp);

    $this->actingAs($this->k->adminPmat);
    expect(TautanBekuRelationManager::canViewForRecord($spj, ViewSpj::class))->toBeTrue();
    Livewire::test(TautanBekuRelationManager::class, ['ownerRecord' => $spj, 'pageClass' => ViewSpj::class])
        ->assertCanSeeTableRecords($spj->tautanBeku()->get());

    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    $this->actingAs($adminPbio);
    expect(TautanBekuRelationManager::canViewForRecord($spj, ViewSpj::class))->toBeFalse();
    $this->get("/panel/spj/{$spj->id}")->assertForbidden();
});
