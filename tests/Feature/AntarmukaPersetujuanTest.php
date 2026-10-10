<?php

use App\Actions\Master\TetapkanPagu;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Filament\Pages\MenungguTindakan;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Filament\Resources\Pengajuans\RelationManagers\LogPerubahanRelationManager;
use App\Filament\Resources\Pengajuans\RelationManagers\RiwayatStatusRelationManager;
use App\Filament\Widgets\AntreanPersetujuanWidget;
use App\Models\Pagu;
use App\Models\Pengajuan;
use Livewire\Livewire;

beforeEach(function () {
    $this->k = siapkanAnggaran('100000000.00');
});

test('admin-keuangan melihat tombol Verifikasi hanya pada status yang tepat', function () {
    $p = buatPengajuanDiajukan($this->k);

    Livewire::actingAs($this->k->keu)->test(ViewPengajuan::class, ['record' => $p->getRouteKey()])
        ->assertActionHidden('verifikasi')
        ->assertActionHidden('setujui');

    app(VerifikasiPengajuan::class)->handle($p, $this->k->kepalaPmat);

    Livewire::actingAs($this->k->keu)->test(ViewPengajuan::class, ['record' => $p->getRouteKey()])
        ->assertActionVisible('verifikasi')
        ->assertActionVisible('kembalikan')
        ->assertActionVisible('batalkanKeuangan')
        ->callAction('verifikasi', data: ['catatan' => ''])
        ->assertNotified('Pengajuan diverifikasi')
        ->assertRedirect(MenungguTindakan::getUrl());

    expect($p->fresh()->status)->toBe(StatusPengajuan::DiverifikasiKeuangan);
});

test('admin-unit tidak melihat tombol keputusan', function () {
    $p = buatPengajuanDiajukan($this->k);

    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $p->getRouteKey()])
        ->assertActionHidden('verifikasi')
        ->assertActionHidden('setujui')
        ->assertActionHidden('kembalikan')
        ->assertActionHidden('tolak')
        ->assertActionHidden('batalkanKeuangan');
});

test('kembalikan tanpa catatan menampilkan galat validasi', function () {
    $p = buatPengajuanDiajukan($this->k);

    Livewire::actingAs($this->k->kepalaPmat)->test(ViewPengajuan::class, ['record' => $p->getRouteKey()])
        ->callAction('kembalikan', data: ['catatan' => ''])
        ->assertHasActionErrors(['catatan' => 'required']);

    expect($p->fresh()->status)->toBe(StatusPengajuan::Diajukan);

    Livewire::actingAs($this->k->kepalaPmat)->test(ViewPengajuan::class, ['record' => $p->getRouteKey()])
        ->callAction('kembalikan', data: ['catatan' => 'Lengkapi rincian volume'])
        ->assertHasNoActionErrors();

    expect($p->fresh()->status)->toBe(StatusPengajuan::Dikembalikan);
});

test('MenungguTindakan kepala-unit PMAT hanya berisi pengajuan PMAT; badge sesuai', function () {
    $pmat = buatPengajuanDiajukan($this->k);

    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    $paguPbio = Pagu::factory()->create(['tahun_anggaran_id' => $this->k->tahun->id, 'unit_id' => $this->k->pbio->id, 'dibuat_oleh' => $this->k->keu->id]);
    app(TetapkanPagu::class)->handle($paguPbio, $this->k->wadek);
    $pbio = app(AjukanPengajuan::class)->handle(buatPengajuanDraf($this->k, oleh: $adminPbio), $adminPbio);

    $this->actingAs($this->k->kepalaPmat);
    Livewire::test(MenungguTindakan::class)
        ->assertCanSeeTableRecords([$pmat])
        ->assertCanNotSeeTableRecords([$pbio]);
    expect(MenungguTindakan::getNavigationBadge())->toBe('1');

    // Admin-keuangan belum punya antrean (tahap kepala unit masih aktif).
    $this->actingAs($this->k->keu);
    expect(MenungguTindakan::getNavigationBadge())->toBeNull();

    app(VerifikasiPengajuan::class)->handle($pmat, $this->k->kepalaPmat);
    expect(MenungguTindakan::getNavigationBadge())->toBe('1');

    $this->actingAs($this->k->adminPmat)->get('/panel/menunggu-tindakan')->assertForbidden();
});

test('MenungguTindakan mengecualikan pengguna yang melanggar pemisahan tugas', function () {
    $ganda = penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $this->k->pmat->id]);
    $ganda->assignRole(Peran::AdminKeuangan->value);
    $p = app(VerifikasiPengajuan::class)->handle(buatPengajuanDiajukan($this->k), $ganda);

    $this->actingAs($ganda);
    Livewire::test(MenungguTindakan::class)->assertCanNotSeeTableRecords([Pengajuan::find($p->id)]);

    $this->actingAs($this->k->keu);
    Livewire::test(MenungguTindakan::class)->assertCanSeeTableRecords([Pengajuan::find($p->id)]);
});

test('riwayat status tampil terurut; log perubahan hanya untuk peran fakultas', function () {
    $p = buatPengajuanDiajukan($this->k);
    app(VerifikasiPengajuan::class)->handle($p, $this->k->kepalaPmat, 'OK dari kaprodi');

    Livewire::actingAs($this->k->adminPmat)
        ->test(RiwayatStatusRelationManager::class, ['ownerRecord' => $p, 'pageClass' => ViewPengajuan::class])
        ->assertCanSeeTableRecords($p->riwayatStatus()->get(), inOrder: true)
        ->assertSee('OK dari kaprodi');

    expect(LogPerubahanRelationManager::canViewForRecord($p, ViewPengajuan::class))->toBeFalse();

    $this->actingAs($this->k->keu);
    expect(LogPerubahanRelationManager::canViewForRecord($p, ViewPengajuan::class))->toBeTrue();
    Livewire::test(LogPerubahanRelationManager::class, ['ownerRecord' => $p, 'pageClass' => ViewPengajuan::class])
        ->assertOk()
        ->assertSee('Item RAB');
});

test('widget antrean di dasbor tampil untuk admin-keuangan & bpp (F11.2)', function () {
    buatPengajuanDiajukan($this->k);

    $this->actingAs($this->k->keu);
    expect(AntreanPersetujuanWidget::canView())->toBeTrue();
    Livewire::test(AntreanPersetujuanWidget::class)->assertOk()->assertSee('Menunggu tindakan saya');

    $this->actingAs($this->k->bpp);
    expect(AntreanPersetujuanWidget::canView())->toBeTrue();

    $this->actingAs($this->k->adminPmat);
    expect(AntreanPersetujuanWidget::canView())->toBeFalse();
});
