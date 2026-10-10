<?php

use App\Actions\Master\TetapkanPagu;
use App\Actions\Master\UbahPaguDitetapkan;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Exceptions\PaguTidakCukupException;
use App\Filament\Resources\Pengajuans\Pages\CreatePengajuan;
use App\Filament\Resources\Pengajuans\Pages\EditPengajuan;
use App\Filament\Resources\Pengajuans\Pages\ListPengajuans;
use App\Filament\Widgets\RingkasanPaguWidget;
use App\Models\Akun;
use App\Models\Pagu;
use App\Models\PaguAkun;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\Unit;
use Database\Seeders\UnitContohSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(UnitContohSeeder::class);
    $this->tahun = buatTahun(2027);
    $this->pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $this->pbio = Unit::where('kode', 'PBIO')->firstOrFail();
    $this->adminPmat = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]);
    $this->akun = Akun::factory()->create();
});

/**
 * Pengajuan berstatus tertentu dengan total kolom tertentu (dibuat langsung untuk skenario komitmen).
 */
function pengajuanDenganTotal(string $tahunId, string $unitId, StatusPengajuan $status, array $total): Pengajuan
{
    return Pengajuan::factory()->create([
        'tahun_anggaran_id' => $tahunId,
        'unit_id' => $unitId,
        'status' => $status,
        ...$total,
    ]);
}

test('admin-unit membuat pengajuan dengan 2 item lewat halaman create; total tersimpan benar', function () {
    Livewire::actingAs($this->adminPmat)->test(CreatePengajuan::class)
        ->fillForm([
            'tahun_anggaran_id' => $this->tahun->id,
            'judul_kegiatan' => 'Seminar Nasional',
            'tanggal_mulai_kegiatan' => '2027-05-01',
            'tanggal_selesai_kegiatan' => '2027-05-02',
            'penanggung_jawab' => 'Kaprodi',
            'items' => [
                ['akun_id' => $this->akun->id, 'uraian' => 'Konsumsi', 'volume' => '50', 'satuan' => 'OK', 'harga_satuan' => '35000'],
                ['akun_id' => $this->akun->id, 'uraian' => 'Honor narasumber', 'volume' => '2', 'satuan' => 'OJ', 'harga_satuan' => '900000.50'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pengajuan = Pengajuan::where('judul_kegiatan', 'Seminar Nasional')->firstOrFail();

    // 1.750.000 + 1.800.001 = 3.550.001
    expect($pengajuan->unit_id)->toBe($this->pmat->id)
        ->and($pengajuan->total_diajukan)->toBe('3550001.00')
        ->and($pengajuan->rabAktif->item()->count())->toBe(2);
});

test('form menolak volume nol dan harga tidak sah', function () {
    Livewire::actingAs($this->adminPmat)->test(CreatePengajuan::class)
        ->fillForm([
            'tahun_anggaran_id' => $this->tahun->id,
            'judul_kegiatan' => 'X',
            'tanggal_mulai_kegiatan' => '2027-05-01',
            'tanggal_selesai_kegiatan' => '2027-05-02',
            'penanggung_jawab' => 'Kaprodi',
            'items' => [['akun_id' => $this->akun->id, 'uraian' => 'A', 'volume' => '0', 'satuan' => 'OK', 'harga_satuan' => '1.234']],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Pengajuan::count())->toBe(0);
});

test('edit memuat item RAB dan menyimpan ulang lewat SimpanDrafPengajuan', function () {
    $pengajuan = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($this->tahun->id), [itemRab($this->akun->id, '2', '150000')], $this->adminPmat);

    Livewire::actingAs($this->adminPmat)->test(EditPengajuan::class, ['record' => $pengajuan->getRouteKey()])
        ->assertSchemaStateSet(fn (array $state) => expect(array_values($state['items'])[0]['volume'])->toBe('2'))
        ->fillForm(['items' => [['akun_id' => $this->akun->id, 'uraian' => 'Baru', 'volume' => '3', 'satuan' => 'OH', 'harga_satuan' => '100000']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pengajuan->fresh()->total_diajukan)->toBe('300000.00');

    $this->actingAs($this->adminPmat)->get("/panel/pengajuan/{$pengajuan->id}")
        ->assertOk()
        ->assertSee('Baru')
        ->assertSee('Rp300.000,00');
});

test('admin-unit tidak melihat pengajuan unit lain di tabel dan URL view → 403', function () {
    $milikPmat = Pengajuan::factory()->create(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => $this->pmat->id]);
    $milikPbio = Pengajuan::factory()->create(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => $this->pbio->id]);

    Livewire::actingAs($this->adminPmat)->test(ListPengajuans::class)
        ->assertCanSeeTableRecords([$milikPmat])
        ->assertCanNotSeeTableRecords([$milikPbio]);

    $this->actingAs($this->adminPmat)->get("/panel/pengajuan/{$milikPbio->id}")->assertForbidden();
    $this->actingAs($this->adminPmat)->get("/panel/pengajuan/{$milikPmat->id}")->assertOk();

    // Pimpinan (lingkup fakultas) melihat keduanya.
    Livewire::actingAs(penggunaDenganPeran(Peran::Pimpinan))->test(ListPengajuans::class)
        ->assertCanSeeTableRecords([$milikPmat, $milikPbio]);
});

test('HitungSisaPagu: pagu 100 jt, diajukan 30, disetujui 20, draf 50, selesai realisasi 5 → komitmen 55, sisa 45', function () {
    $pagu = Pagu::factory()->create(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => $this->pmat->id, 'jumlah' => '100000000.00']);
    $aksi = app(HitungSisaPagu::class);

    // Pagu belum ditetapkan → 0.
    expect($aksi->handle($this->tahun->id, $this->pmat->id)['pagu'])->toBe('0.00');

    app(TetapkanPagu::class)->handle($pagu, penggunaDenganPeran(Peran::WadekKeuangan));

    pengajuanDenganTotal($this->tahun->id, $this->pmat->id, StatusPengajuan::Diajukan, ['total_diajukan' => '30000000.00']);
    pengajuanDenganTotal($this->tahun->id, $this->pmat->id, StatusPengajuan::Disetujui, ['total_diajukan' => '25000000.00', 'total_disetujui' => '20000000.00']);
    $draf = pengajuanDenganTotal($this->tahun->id, $this->pmat->id, StatusPengajuan::Draf, ['total_diajukan' => '50000000.00']);
    pengajuanDenganTotal($this->tahun->id, $this->pmat->id, StatusPengajuan::Selesai, ['total_disetujui' => '8000000.00', 'total_realisasi' => '5000000.00']);
    pengajuanDenganTotal($this->tahun->id, $this->pbio->id, StatusPengajuan::Diajukan, ['total_diajukan' => '99000000.00']);

    expect($aksi->handle($this->tahun->id, $this->pmat->id))->toBe([
        'pagu' => '100000000.00',
        'komitmen' => '55000000.00',
        'sisa' => '45000000.00',
    ]);

    // Pengecualian pengajuan sendiri.
    $diajukan = Pengajuan::where('status', 'diajukan')->where('unit_id', $this->pmat->id)->first();
    expect($aksi->handle($this->tahun->id, $this->pmat->id, kecualiPengajuanId: $diajukan->id)['komitmen'])->toBe('25000000.00')
        ->and($draf->status->menghitungKomitmen())->toBeNull();

    // UbahPaguDitetapkan ke 50 jt (< komitmen 55 jt) ditolak.
    expect(fn () => app(UbahPaguDitetapkan::class)->handle($pagu, '50000000', 'Pemotongan anggaran', penggunaDenganPeran(Peran::WadekKeuangan)))
        ->toThrow(PaguTidakCukupException::class);
    expect($pagu->fresh()->jumlah)->toBe('100000000.00');
});

test('HitungSisaPagu level akun memakai rincian pagu_akun dan item RAB aktif', function () {
    $pagu = Pagu::factory()->create(['tahun_anggaran_id' => $this->tahun->id, 'unit_id' => $this->pmat->id, 'jumlah' => '10000000.00']);
    PaguAkun::create(['pagu_id' => $pagu->id, 'akun_id' => $this->akun->id, 'jumlah' => '4000000.00']);
    app(TetapkanPagu::class)->handle($pagu, penggunaDenganPeran(Peran::WadekKeuangan));
    $lain = Akun::factory()->create();

    $p = pengajuanDenganTotal($this->tahun->id, $this->pmat->id, StatusPengajuan::Diajukan, ['total_diajukan' => '1500000.00']);
    $rab = Rab::factory()->create(['pengajuan_id' => $p->id]);
    $p->forceFill(['rab_aktif_id' => $rab->id])->save();
    RabItem::factory()->create(['rab_id' => $rab->id, 'akun_id' => $this->akun->id, 'jumlah' => '1000000.00']);
    RabItem::factory()->create(['rab_id' => $rab->id, 'akun_id' => $lain->id, 'jumlah' => '500000.00']);

    expect(app(HitungSisaPagu::class)->handle($this->tahun->id, $this->pmat->id, $this->akun->id))
        ->toBe(['pagu' => '4000000.00', 'komitmen' => '1000000.00', 'sisa' => '3000000.00'])
        ->and(app(HitungSisaPagu::class)->handle($this->tahun->id, $this->pmat->id, $lain->id))->toBeNull();
});

test('widget ringkasan pagu tampil untuk admin-unit dan peran fakultas, tidak untuk pengguna unit tanpa unit', function () {
    $this->actingAs($this->adminPmat);
    expect(RingkasanPaguWidget::canView())->toBeTrue();
    Livewire::test(RingkasanPaguWidget::class)->assertOk();

    $this->actingAs(penggunaDenganPeran(Peran::AdminKeuangan));
    expect(RingkasanPaguWidget::canView())->toBeTrue();

    $this->actingAs(penggunaDenganPeran(Peran::AdminUnit));
    expect(RingkasanPaguWidget::canView())->toBeFalse();
});
