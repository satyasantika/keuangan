<?php

use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Enums\Peran;
use App\Enums\StatusPengembalian;
use App\Enums\StatusSpj;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Filament\Resources\Pengembalians\Pages\ListPengembalians;
use App\Filament\Resources\Spjs\Pages\ViewSpj;
use App\Filament\Resources\Spjs\RelationManagers\RealisasiItemRelationManager;
use App\Filament\Resources\Spjs\Schemas\SpjInfolist;
use App\Models\Pengembalian;
use App\Models\RealisasiItem;
use App\Models\Spj;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
});

test('admin-unit menyusun SPJ dari pengajuan lalu menambah realisasi dengan tautan kuitansi lewat relation manager', function () {
    $p = buatPengajuanDicairkan($this->k);

    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $p->getRouteKey()])
        ->assertActionVisible('bukaSpj')
        ->callAction('bukaSpj');

    $spj = Spj::where('pengajuan_id', $p->id)->firstOrFail();
    $item = $p->rabAktif->item()->first();

    Livewire::actingAs($this->k->adminPmat)
        ->test(RealisasiItemRelationManager::class, ['ownerRecord' => $spj, 'pageClass' => ViewSpj::class])
        ->callTableAction('create', data: [
            'rab_item_id' => $item->id,
            'tanggal_bukti' => '2027-06-18',
            'nomor_bukti' => 'KW-77',
            'uraian' => 'Konsumsi',
            'volume' => '1',
            'harga_satuan' => '120000',
            'tautan' => [['jenis' => 'kuitansi', 'label' => 'Kuitansi', 'url' => 'https://drive.google.com/file/d/1Kuit/view?usp=sharing', 'akses_terbatas_dikonfirmasi' => true]],
            'potongan_pajak' => [],
        ])
        ->assertHasNoTableActionErrors();

    $r = RealisasiItem::firstOrFail();
    expect($r->jumlah_bruto)->toBe('120000.00')
        ->and($r->tautanBerkas()->first()->drive_file_id)->toBe('1Kuit');

    expect(SpjInfolist::ringkasanItem($spj->fresh()))->toBe([
        ['uraian' => 'Konsumsi peserta', 'jumlah' => 'Rp300.000,00', 'terealisasi' => 'Rp120.000,00', 'sisa' => 'Rp180.000,00'],
    ]);

    $this->actingAs($this->k->adminPmat)->get("/panel/spj/{$spj->id}")->assertOk()->assertSee('Rp180.000,00');
});

test('verifikator melihat lencana bukti ganda; admin-unit unit lain tidak melihat SPJ', function () {
    $a = buatPengajuanDicairkan($this->k);
    $b = buatPengajuanDicairkan($this->k);
    [$d1, $t1] = dataRealisasi($a, '1', '100000', '1Ganda');
    app(CatatRealisasi::class)->handle($a, $d1, $t1, $this->k->adminPmat);
    [$d2, $t2] = dataRealisasi($b, '1', '100000', '1Ganda');
    $ganda = app(CatatRealisasi::class)->handle($b, $d2, $t2, $this->k->adminPmat);
    $spj = $ganda->spj;

    Livewire::actingAs($this->k->keu)
        ->test(RealisasiItemRelationManager::class, ['ownerRecord' => $spj, 'pageClass' => ViewSpj::class])
        ->assertSee('Kemungkinan bukti ganda');

    Livewire::actingAs($this->k->adminPmat)
        ->test(RealisasiItemRelationManager::class, ['ownerRecord' => $spj, 'pageClass' => ViewSpj::class])
        ->assertDontSee('Kemungkinan bukti ganda');

    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    $this->actingAs($adminPbio)->get("/panel/spj/{$spj->id}")->assertForbidden();
});

test('tombol Terima tidak tampil untuk pembuat SPJ; tampil untuk BPP; ajukan wajib centang pernyataan', function () {
    $this->k->adminPmat->assignRole(Peran::Bpp->value);
    $spj = buatSpjDenganRealisasi($this->k, '300000');

    Livewire::actingAs($this->k->adminPmat)->test(ViewSpj::class, ['record' => $spj->getRouteKey()])
        ->callAction('ajukanSpj', data: ['pernyataan_final' => false])
        ->assertHasActionErrors(['pernyataan_final']);

    Livewire::actingAs($this->k->adminPmat)->test(ViewSpj::class, ['record' => $spj->getRouteKey()])
        ->callAction('ajukanSpj', data: ['pernyataan_final' => true])
        ->assertNotified('SPJ SPJ/2027/0001 diajukan');

    Livewire::actingAs($this->k->adminPmat)->test(ViewSpj::class, ['record' => $spj->getRouteKey()])
        ->assertActionHidden('terimaSpj');

    Livewire::actingAs($this->k->bpp)->test(ViewSpj::class, ['record' => $spj->getRouteKey()])
        ->assertActionVisible('terimaSpj')
        ->callAction('terimaSpj', data: ['catatan' => 'Lengkap']);

    expect($spj->fresh()->status)->toBe(StatusSpj::Diterima);
});

test('admin-unit mencatat pengembalian dari halaman SPJ; BPP memverifikasi dari daftar', function () {
    $spj = buatSpjDenganRealisasi($this->k, '250000');
    $spj = app(AjukanSpj::class)->handle($spj, $this->k->adminPmat, true);

    Livewire::actingAs($this->k->adminPmat)->test(ViewSpj::class, ['record' => $spj->getRouteKey()])
        ->assertActionVisible('catatPengembalian')
        ->mountAction('catatPengembalian')
        ->assertActionDataSet(['jumlah' => '50000.00'])
        ->setActionData([
            'tanggal_setor' => '2027-06-19',
            'nomor_bukti_setor' => 'NTPN-1',
            'tautan_bukti' => [['jenis' => 'bukti_setor', 'label' => 'Bukti setor', 'url' => 'https://drive.google.com/file/d/1Setor/view', 'akses_terbatas_dikonfirmasi' => true]],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $k = Pengembalian::firstOrFail();

    Livewire::actingAs($this->k->bpp)->test(ListPengembalians::class)
        ->assertCanSeeTableRecords([$k])
        ->callTableAction('verifikasiPengembalian', $k);

    expect($k->fresh()->status)->toBe(StatusPengembalian::Diverifikasi);
});
