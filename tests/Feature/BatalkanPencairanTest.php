<?php

use App\Actions\Pencairan\BatalkanPencairan;
use App\Actions\Pencairan\CatatPencairan;
use App\Enums\Peran;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPeriode;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PeriodeTerkunciException;
use App\Filament\Resources\Pencairans\Pages\ListPencairans;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Filament\Resources\Pengajuans\RelationManagers\PencairanRelationManager;
use App\Models\JenisPencairan;
use App\Models\Pencairan;
use App\Models\Periode;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->p = buatPengajuanDisetujui($this->k);
});

function cairkan(object $test, string $jumlah = '300000', string $tanggal = '2027-06-15'): Pencairan
{
    [$data, $tautan] = dataPencairan($jumlah, $tanggal);

    return app(CatatPencairan::class)->handle($test->p->fresh(), $data, $tautan, $test->k->bpp);
}

test('batalkan pencairan satu-satunya → pengajuan kembali disetujui dan batas SPJ dikosongkan', function () {
    $c = cairkan($this);
    expect($this->p->fresh()->status)->toBe(StatusPengajuan::Dicairkan);

    app(BatalkanPencairan::class)->handle($c, 'Salah catat nominal transfer', $this->k->bpp);

    $p = $this->p->fresh();
    expect($c->fresh()->status)->toBe(StatusPencairan::Dibatalkan)
        ->and($c->fresh()->dibatalkan_oleh)->toBe($this->k->bpp->id)
        ->and($p->status)->toBe(StatusPengajuan::Disetujui)
        ->and($p->batas_spj)->toBeNull()
        ->and($p->total_dicairkan)->toBe('0.00');
});

test('masih ada pencairan lain → pengajuan tetap dicairkan', function () {
    $a = cairkan($this, '100000');
    cairkan($this, '100000', '2027-06-16');

    app(BatalkanPencairan::class)->handle($a, 'Salah catat nominal transfer', $this->k->bpp);

    expect($this->p->fresh()->status)->toBe(StatusPengajuan::Dicairkan)
        ->and($this->p->fresh()->total_dicairkan)->toBe('100000.00');
});

test('pembatalan tanpa alasan → gagal; periode terkunci → gagal; admin-unit → tidak berhak', function () {
    $c = cairkan($this);

    expect(fn () => app(BatalkanPencairan::class)->handle($c, '', $this->k->bpp))->toThrow(KesalahanDomain::class)
        ->and(fn () => app(BatalkanPencairan::class)->handle($c, 'Salah catat nominal', $this->k->adminPmat))
        ->toThrow(AuthorizationException::class);

    Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 6)->first()->forceFill(['status' => StatusPeriode::Terkunci])->save();
    expect(fn () => app(BatalkanPencairan::class)->handle($c->fresh(), 'Salah catat nominal', $this->k->bpp))->toThrow(PeriodeTerkunciException::class);
    expect($c->fresh()->status)->toBe(StatusPencairan::Dicatat);
});

test('pencairan tidak dapat dihapus (policy delete false, tanpa route hapus)', function () {
    $c = cairkan($this);

    expect($this->k->bpp->can('delete', $c))->toBeFalse()
        ->and($this->k->keu->can('delete', $c))->toBeFalse();

    Livewire::actingAs($this->k->bpp)->test(ListPencairans::class)->assertTableActionDoesNotExist('delete');
    $this->actingAs($this->k->bpp)->delete("/panel/pencairan/{$c->id}")->assertStatus(405);
});

test('admin-unit melihat pencairan unitnya tetapi tidak tombol Batalkan', function () {
    $c = cairkan($this);

    Livewire::actingAs($this->k->adminPmat)
        ->test(PencairanRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPengajuan::class])
        ->assertCanSeeTableRecords([$c])
        ->assertTableActionHidden('batalkanPencairan', $c);

    Livewire::actingAs($this->k->bpp)
        ->test(PencairanRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPengajuan::class])
        ->assertTableActionVisible('batalkanPencairan', $c)
        ->callTableAction('batalkanPencairan', $c, data: ['alasan' => 'Salah catat nominal transfer']);

    expect($c->fresh()->status)->toBe(StatusPencairan::Dibatalkan);

    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    Livewire::actingAs($adminPbio)->test(ListPencairans::class)->assertCanNotSeeTableRecords([$c]);
    $this->actingAs($adminPbio)->get("/panel/pencairan/{$c->id}")->assertForbidden();
});

test('filter periode berfungsi', function () {
    $juni = cairkan($this, '100000', '2027-06-15');
    $mei = cairkan($this, '100000', '2027-05-15');
    $periodeMei = Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 5)->value('id');

    Livewire::actingAs($this->k->bpp)->test(ListPencairans::class)
        ->filterTable('periode_id', $periodeMei)
        ->assertCanSeeTableRecords([$mei])
        ->assertCanNotSeeTableRecords([$juni]);
});

test('BPP mencatat pencairan dari halaman pengajuan dengan jumlah bawaan sisa', function () {
    Livewire::actingAs($this->k->bpp)->test(ViewPengajuan::class, ['record' => $this->p->getRouteKey()])
        ->assertActionVisible('catatPencairan')
        ->mountAction('catatPencairan')
        ->assertActionDataSet(['jumlah' => '300000.00'])
        ->setActionData([
            'jenis_pencairan_id' => JenisPencairan::firstOrCreate(['kode' => 'LS'], ['nama' => 'Pembayaran Langsung'])->id,
            'tanggal' => '2027-06-18',
            'nomor_bukti' => 'TRF-01',
            'tautan_bukti' => [['jenis' => 'bukti_pencairan', 'label' => 'Bukti transfer', 'url' => 'https://drive.google.com/file/d/1Trf/view', 'akses_terbatas_dikonfirmasi' => true]],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Pencairan dicatat');

    expect($this->p->fresh()->status)->toBe(StatusPengajuan::Dicairkan);

    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $this->p->getRouteKey()])
        ->assertActionHidden('catatPencairan')
        ->assertSee('Ringkasan dana');
});
