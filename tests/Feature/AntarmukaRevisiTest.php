<?php

use App\Actions\Revisi\AjukanRevisiRab;
use App\Actions\Revisi\BuatRevisiRab;
use App\Actions\Revisi\SimpanItemRevisi;
use App\Actions\Revisi\VerifikasiRevisiRab;
use App\Actions\Spj\CatatRealisasi;
use App\Enums\Peran;
use App\Filament\Pages\MenungguTindakan;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Filament\Resources\Pengajuans\RelationManagers\RabRelationManager;
use App\Filament\Resources\RevisiRabs\Pages\ListRevisiRabs;
use App\Filament\Widgets\RevisiMenungguWidget;
use App\Models\Rab;
use App\Queries\PerbandinganRabQuery;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('10000000.00');
    $this->p = buatPengajuanDicairkan($this->k);
});

test('admin-unit membuat revisi dari halaman pengajuan', function () {
    Livewire::actingAs($this->k->adminPmat)
        ->test(RabRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPengajuan::class])
        ->assertTableActionVisible('buatRevisi')
        ->callTableAction('buatRevisi', data: ['alasan' => 'Penyesuaian jumlah peserta'])
        ->assertHasNoTableActionErrors();

    expect(Rab::where('pengajuan_id', $this->p->id)->where('nomor_revisi', 1)->exists())->toBeTrue();
});

test('tabel perbandingan menunjukkan selisih yang benar (ubah, tambah, terealisasi)', function () {
    [$data, $tautan] = dataRealisasi($this->p, '1', '100000');
    app(CatatRealisasi::class)->handle($this->p, $data, $tautan, $this->k->adminPmat);

    $revisi = app(BuatRevisiRab::class)->handle($this->p->fresh(), 'Penyesuaian jumlah peserta', $this->k->adminPmat);
    $item = $revisi->item()->first();
    app(SimpanItemRevisi::class)->handle($revisi, [
        ['id' => $item->id, 'akun_id' => $item->akun_id, 'uraian' => $item->uraian, 'volume' => '3', 'satuan' => 'OH', 'harga_satuan' => '150000'],
        ['akun_id' => $this->k->akun->id, 'uraian' => 'Cetak modul', 'volume' => '10', 'satuan' => 'eks', 'harga_satuan' => '20000'],
    ], $this->k->adminPmat);

    $hasil = PerbandinganRabQuery::bandingkan($revisi->fresh());

    expect($hasil['baris'])->toHaveCount(2)
        ->and($hasil['baris'][0])->toMatchArray(['tanda' => 'ubah', 'lama' => '300000.00', 'baru' => '450000.00', 'selisih' => '150000.00', 'terealisasi' => '100000.00'])
        ->and($hasil['baris'][1])->toMatchArray(['tanda' => 'tambah', 'lama' => '0.00', 'baru' => '200000.00', 'selisih' => '200000.00'])
        ->and($hasil['total_lama'])->toBe('300000.00')
        ->and($hasil['total_baru'])->toBe('650000.00')
        ->and($hasil['total_selisih'])->toBe('350000.00');

    Livewire::actingAs($this->k->adminPmat)
        ->test(RabRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPengajuan::class])
        ->assertTableActionVisible('bandingkan', $revisi);

    expect(view('filament.perbandingan-rab', ['perbandingan' => $hasil])->render())
        ->toContain('Rp350.000,00')
        ->toContain('Tambah');
});

test('wadek melihat revisi di MenungguTindakan; admin-unit tidak melihat tombol Setujui', function () {
    $revisi = app(BuatRevisiRab::class)->handle($this->p, 'Penyesuaian jumlah peserta', $this->k->adminPmat);
    $revisi = app(AjukanRevisiRab::class)->handle($revisi, $this->k->adminPmat);

    $this->actingAs($this->k->keu);
    expect(MenungguTindakan::getNavigationBadge())->toBe('1');
    Livewire::test(RevisiMenungguWidget::class)->assertCanSeeTableRecords([$revisi]);

    $revisi = app(VerifikasiRevisiRab::class)->handle($revisi, $this->k->keu);

    $this->actingAs($this->k->wadek);
    Livewire::test(RevisiMenungguWidget::class)
        ->assertCanSeeTableRecords([$revisi])
        ->assertTableActionVisible('setujuiRevisi', $revisi);
    $this->get('/panel/menunggu-tindakan')->assertOk();

    Livewire::actingAs($this->k->adminPmat)
        ->test(RabRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPengajuan::class])
        ->assertTableActionHidden('setujuiRevisi', $revisi);

    Livewire::actingAs($this->k->wadek)->test(ListRevisiRabs::class)
        ->callTableAction('setujuiRevisi', $revisi)
        ->assertNotified('Revisi disetujui dan berlaku');

    expect($this->p->fresh()->rab_aktif_id)->toBe($revisi->id);
});

test('PDF lembar revisi dapat dicetak pemilik; unit lain 403', function () {
    $revisi = app(BuatRevisiRab::class)->handle($this->p, 'Penyesuaian jumlah peserta', $this->k->adminPmat);

    $respons = $this->actingAs($this->k->adminPmat)->get("/revisi/{$revisi->id}/pdf");
    $respons->assertOk();
    expect($respons->headers->get('Content-Type'))->toBe('application/pdf');

    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    $this->actingAs($adminPbio)->get("/revisi/{$revisi->id}/pdf")->assertForbidden();
});
