<?php

use App\Actions\Spj\CatatRealisasi;
use App\Enums\Peran;
use App\Enums\StatusEkspor;
use App\Filament\Pages\Laporan;
use App\Jobs\BuatEksporLaporan;
use App\Models\LaporanEkspor;
use App\Models\User;
use App\Notifications\EksporSiap;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\SimpleExcel\SimpleExcelReader;

beforeEach(function () {
    $this->travelTo('2027-08-01 10:00:00');
    $this->k = siapkanAnggaran('10000000.00');
    $p = buatPengajuanDicairkan($this->k);
    [$d, $t] = dataRealisasi($p, '1', '250000', tanggal: '2027-06-16');
    app(CatatRealisasi::class)->handle($p, $d, $t, $this->k->adminPmat);
    $this->param = ['tahun_anggaran_id' => $this->k->tahun->id, 'jenis_periode' => 'tahunan'];
});

function buatEkspor(object $test, string $format = 'xlsx', ?User $oleh = null): LaporanEkspor
{
    $ekspor = LaporanEkspor::create(['jenis' => 'LAP-01', 'parameter' => $test->param, 'format' => $format, 'diminta_oleh' => ($oleh ?? $test->k->keu)->id]);
    BuatEksporLaporan::dispatch($ekspor);

    return $ekspor->fresh();
}

test('ekspor xlsx LAP-01 lewat antrean menghasilkan baris unit dan total benar + notifikasi siap', function () {
    Notification::fake();
    $ekspor = buatEkspor($this);

    expect($ekspor->status)->toBe(StatusEkspor::Selesai)
        ->and($ekspor->path)->toBe("tmp/ekspor/{$ekspor->id}.xlsx")
        ->and($ekspor->kedaluwarsa_pada->diffInHours(now(), true))->toBeLessThanOrEqual(24.0);

    $baris = SimpleExcelReader::create(storage_path('app/'.$ekspor->path))->getRows()->all();
    $pmat = collect($baris)->firstWhere('Unit', 'PMAT');
    $total = collect($baris)->firstWhere('Unit', 'TOTAL');

    expect($pmat['Pagu'])->toEqual(10000000)
        ->and($pmat['Realisasi'])->toEqual(250000)
        ->and($total['Realisasi kumulatif'])->toEqual(250000);

    Notification::assertSentTo($this->k->keu, EksporSiap::class);
    File::delete(storage_path('app/'.$ekspor->path));
});

test('ekspor pdf dibuat', function () {
    $ekspor = buatEkspor($this, 'pdf');

    expect(substr((string) file_get_contents(storage_path('app/'.$ekspor->path)), 0, 4))->toBe('%PDF');
    File::delete(storage_path('app/'.$ekspor->path));
});

test('unduhan: pemilik bisa; pengguna lain 403 walau punya tautan; tanpa signature 403', function () {
    $ekspor = buatEkspor($this);
    $tautan = URL::temporarySignedRoute('ekspor.unduh', now()->addHour(), ['laporanEkspor' => $ekspor->id]);

    $this->actingAs($this->k->keu)->get($tautan)->assertOk();
    $this->actingAs(penggunaDenganPeran(Peran::AdminKeuangan))->get($tautan)->assertForbidden();
    $this->actingAs($this->k->keu)->get("/ekspor/{$ekspor->id}")->assertForbidden();
    File::delete(storage_path('app/'.$ekspor->path));
});

test('berkas ekspor > 24 jam terhapus, status kedaluwarsa, unduhan → 410', function () {
    $ekspor = buatEkspor($this);
    touch(storage_path('app/'.$ekspor->path), now()->subHours(25)->getTimestamp());

    $this->artisan('keuangan:bersihkan-sementara')->assertSuccessful();

    expect($ekspor->fresh()->status)->toBe(StatusEkspor::Kedaluwarsa)
        ->and(File::exists(storage_path("app/tmp/ekspor/{$ekspor->id}.xlsx")))->toBeFalse();

    $this->actingAs($this->k->keu)->get(URL::temporarySignedRoute('ekspor.unduh', now()->addHour(), ['laporanEkspor' => $ekspor->id]))->assertStatus(410);
});

test('laporan kecil di-stream tanpa berkas tersisa dan tanpa baris laporan_ekspor', function () {
    $sebelum = File::isDirectory(storage_path('app/tmp/ekspor')) ? count(File::files(storage_path('app/tmp/ekspor'))) : 0;

    $respons = $this->actingAs($this->k->keu)->get(route('laporan.ekspor', ['jenis' => 'LAP-01', 'format' => 'xlsx', ...$this->param]));
    $respons->assertOk();
    $isi = $respons->streamedContent();

    expect(substr($isi, 0, 2))->toBe('PK')
        ->and(LaporanEkspor::count())->toBe(0)
        ->and(File::isDirectory(storage_path('app/tmp/ekspor')) ? count(File::files(storage_path('app/tmp/ekspor'))) : 0)->toBe($sebelum);

    $pdf = $this->actingAs($this->k->keu)->get(route('laporan.ekspor', ['jenis' => 'LAP-01', 'format' => 'pdf', ...$this->param]));
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf');
});

test('ekspor ke-6 dalam semenit → 429', function () {
    $url = route('laporan.ekspor', ['jenis' => 'LAP-02', 'format' => 'xlsx', ...$this->param]);

    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($this->k->keu)->get($url)->assertOk();
    }

    $this->actingAs($this->k->keu)->get($url)->assertStatus(429);
});

test('admin-unit hanya dapat mengekspor unitnya; laporan tak berhak ditolak', function () {
    $respons = $this->actingAs($this->k->adminPmat)->get(route('laporan.ekspor', ['jenis' => 'LAP-01', 'format' => 'xlsx', 'unit_id' => $this->k->pbio->id, ...$this->param]));
    $respons->assertOk();

    $jalur = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
    file_put_contents($jalur, $respons->streamedContent());
    $unit = collect(SimpleExcelReader::create($jalur)->getRows()->all())->pluck('Unit')->filter(fn ($u) => in_array($u, ['PMAT', 'PBIO'], true))->values()->all();
    unlink($jalur);

    expect($unit)->toBe(['PMAT']);

    $this->actingAs($this->k->adminPmat)->get(route('laporan.ekspor', ['jenis' => 'LAP-02', 'format' => 'xlsx', ...$this->param]))->assertForbidden();
});

test('halaman laporan menampilkan pratinjau', function () {
    Livewire\Livewire::actingAs($this->k->keu)->test(Laporan::class)
        ->assertSee('Laporan Realisasi Anggaran per Unit')
        ->assertSee('Rp250.000,00');

    $this->actingAs($this->k->keu)->get('/panel/riwayat-ekspor')->assertOk();
});
