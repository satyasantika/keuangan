<?php

use App\Actions\Spj\CatatRealisasi;
use App\Enums\Peran;
use App\Filament\Pages\Dasbor;
use App\Filament\Widgets\RingkasanPaguWidget;
use App\Filament\Widgets\SerapanPerUnitChart;
use App\Filament\Widgets\SpjJatuhTempoWidget;
use App\Filament\Widgets\TrenRealisasiBulananChart;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2027-08-01 10:00:00');
    $this->k = siapkanAnggaran('10000000.00');
    $p = buatPengajuanDicairkan($this->k); // RAB 300.000, cair 15 Juni
    [$d, $t] = dataRealisasi($p, '1', '100000', tanggal: '2027-06-16');
    app(CatatRealisasi::class)->handle($p, $d, $t, $this->k->adminPmat);
    $this->pimpinan = penggunaDenganPeran(Peran::Pimpinan);
});

test('pimpinan melihat SerapanPerUnitChart; admin-unit tidak', function () {
    $this->actingAs($this->pimpinan);
    expect(SerapanPerUnitChart::canView())->toBeTrue();
    Livewire::test(SerapanPerUnitChart::class)->assertOk();

    $this->actingAs($this->k->adminPmat);
    expect(SerapanPerUnitChart::canView())->toBeFalse()
        ->and(TrenRealisasiBulananChart::canView())->toBeTrue()
        ->and(SpjJatuhTempoWidget::canView())->toBeTrue();
});

test('data widget admin-unit hanya unitnya; filter semester mengubah angka', function () {
    $this->actingAs($this->k->adminPmat);

    Livewire::test(RingkasanPaguWidget::class, ['pageFilters' => ['jenis_periode' => 'tahunan']])
        ->assertSee('Rp10.000.000,00')
        ->assertSee('Rp100.000,00');

    Livewire::test(RingkasanPaguWidget::class, ['pageFilters' => ['jenis_periode' => 'semester', 'nilai_periode' => 2]])
        ->assertDontSee('Rp100.000,00')
        ->assertSee('Rp0,00');

    $this->actingAs($this->pimpinan);
    Livewire::test(RingkasanPaguWidget::class)->assertSee('Rp10.000.000,00');
});

test('halaman dasbor dimuat dengan jumlah query terbatas', function () {
    $this->actingAs($this->pimpinan)->get('/panel')->assertOk();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->actingAs($this->pimpinan)->get('/panel')->assertOk()->assertSee('Dasbor');
    Livewire::actingAs($this->pimpinan)->test(RingkasanPaguWidget::class)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(30);
});

test('dasbor menggantikan halaman bawaan dan dapat dibuka semua peran', function () {
    foreach ([Peran::AdminKeuangan, Peran::Bpp, Peran::WadekKeuangan, Peran::KepalaUnit, Peran::AdminUnit] as $peran) {
        $pengguna = penggunaDenganPeran($peran, in_array($peran, Peran::wajibUnit(), true) ? ['unit_id' => $this->k->pmat->id] : []);
        $this->actingAs($pengguna)->get('/panel')->assertOk();
    }

    expect(Livewire::actingAs($this->pimpinan)->test(Dasbor::class)->instance()->getWidgets())->toContain(SerapanPerUnitChart::class);
});
