<?php

use App\Actions\Impor\BatalkanBatchHonor;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Data\ParameterLaporan;
use App\Filament\Resources\RekapHonorEksternals\Pages\ListRekapHonorEksternals;
use App\Jobs\ImporRekapHonor;
use App\Laporan\KatalogLaporan;
use App\Models\Periode;
use App\Models\RekapHonorEksternal;
use App\Queries\LaporanRealisasiQuery;
use App\Support\Pengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelWriter;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
});

/**
 * @param  list<array<string, mixed>>  $baris
 */
function berkasHonor(array $baris): string
{
    $folder = storage_path('app/tmp/impor');
    @mkdir($folder, 0775, true);
    $jalur = $folder.'/uji-'.Str::uuid7().'.xlsx';
    SimpleExcelWriter::create($jalur)->addRows($baris)->close();

    return $jalur;
}

/**
 * @return array<string, mixed>
 */
function barisHonor(int $bulan = 5, string $prodi = 'PMAT', string $jenis = 'sidang', string $bruto = '1000000.00', string $pajak = '50000.00', ?string $neto = null): array
{
    return [
        'tahun' => 2027, 'bulan' => $bulan, 'kode_prodi' => $prodi, 'jenis_ujian' => $jenis, 'jumlah_kegiatan' => 4,
        'jumlah_bruto' => $bruto, 'jumlah_pajak' => $pajak, 'jumlah_neto' => $neto ?? bcsub($bruto, $pajak, 2),
    ];
}

function imporHonor(object $k, array $baris): array
{
    $jalur = berkasHonor($baris);
    $hasil = (new ImporRekapHonor($jalur, 'rekap-fkipapp.xlsx', $k->keu))->proses();
    expect(file_exists($jalur))->toBeFalse();

    return $hasil;
}

test('impor berkas valid 6 baris → 6 baris aktif satu batch dan berkas tmp terhapus', function () {
    $hasil = imporHonor($this->k, [
        barisHonor(4, 'PMAT', 'proposal'), barisHonor(4, 'PMAT', 'seminar'), barisHonor(5, 'PMAT', 'sidang'),
        barisHonor(5, 'PBIO', 'sidang'), barisHonor(6, 'PBIO', 'lainnya'), barisHonor(6, 'XYZ', 'sidang'),
    ]);

    expect($hasil['galat'])->toBe([])
        ->and($hasil['jumlah'])->toBe(6)
        ->and($hasil['peringatan'])->toHaveCount(1);

    $rekap = RekapHonorEksternal::all();
    expect($rekap)->toHaveCount(6)
        ->and($rekap->pluck('batch_impor')->unique())->toHaveCount(1)
        ->and($rekap->pluck('status')->unique()->all())->toBe(['aktif'])
        ->and($rekap->whereNull('unit_id'))->toHaveCount(1)
        ->and($rekap->first()->nama_berkas_sumber)->toBe('rekap-fkipapp.xlsx');
});

test('satu baris neto salah → seluruh batch ditolak', function () {
    $hasil = imporHonor($this->k, [barisHonor(4), barisHonor(5, neto: '999999.00'), barisHonor(6)]);

    expect($hasil['batch'])->toBeNull()
        ->and($hasil['galat'][0])->toContain('Baris 3')->toContain('neto')
        ->and(RekapHonorEksternal::count())->toBe(0);
});

test('periode terkunci → batch ditolak', function () {
    Periode::query()->where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 3)->toBase()->update(['status' => 'terkunci']);

    $hasil = imporHonor($this->k, [barisHonor(3), barisHonor(4)]);

    expect($hasil['batch'])->toBeNull()
        ->and($hasil['galat'][0])->toContain('terkunci')
        ->and(RekapHonorEksternal::count())->toBe(0);
});

test('batalkan batch → tidak muncul di dasbor dan laporan', function () {
    $hasil = imporHonor($this->k, [barisHonor(4), barisHonor(5)]);
    $parameter = new ParameterLaporan($this->k->tahun->id);

    expect(LaporanRealisasiQuery::honorUjian($parameter, $this->k->keu))->toBe('2000000.00')
        ->and(KatalogLaporan::bangun('LAP-09', ['tahun_anggaran_id' => $this->k->tahun->id], $this->k->keu)->baris)->toHaveCount(2);

    expect(fn () => app(BatalkanBatchHonor::class)->handle($hasil['batch'], $this->k->bpp))->toThrow(AuthorizationException::class);
    expect(app(BatalkanBatchHonor::class)->handle($hasil['batch'], $this->k->keu))->toBe(2);

    expect(LaporanRealisasiQuery::honorUjian($parameter, $this->k->keu))->toBe('0.00')
        ->and(KatalogLaporan::bangun('LAP-09', ['tahun_anggaran_id' => $this->k->tahun->id], $this->k->keu)->baris)->toBe([]);
});

test('honor tidak mengurangi sisa pagu bila pengaturan false dan mengurangi bila true', function () {
    imporHonor($this->k, [barisHonor(4, bruto: '3000000.00', pajak: '0.00')]);
    $hitung = fn () => app(HitungSisaPagu::class)->handle($this->k->tahun->id, $this->k->pmat->id)['sisa'];

    expect($hitung())->toBe('100000000.00');

    Pengaturan::set('honor_mengurangi_pagu', true, $this->k->wadek);

    expect($hitung())->toBe('97000000.00');
});

test('tidak ada kolom per dosen/mahasiswa di tabel rekap honor', function () {
    $kolom = Schema::getColumnListing('rekap_honor_eksternal');

    foreach ($kolom as $nama) {
        expect($nama)->not->toMatch('/dosen|mahasiswa|nim|nip|nidn|nama_penerima/');
    }
});

test('admin-keuangan mengunggah berkas lewat panel → job antre, admin-unit tidak melihat tombol impor', function () {
    Queue::fake();
    $this->travelBack(); // berkas sementara Livewire divalidasi terhadap jam nyata
    $isi = file_get_contents(berkasHonor([barisHonor(4)]));

    Livewire::actingAs($this->k->keu)->test(ListRekapHonorEksternals::class)
        ->mountAction('imporRekapHonor')
        ->fillForm(['berkas' => UploadedFile::fake()->createWithContent('rekap.xlsx', $isi)])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    Queue::assertPushedOn('berat', ImporRekapHonor::class);

    Livewire::actingAs($this->k->kepalaPmat)->test(ListRekapHonorEksternals::class)->assertActionHidden('imporRekapHonor');
});

test('baris rekap honor tidak dapat diubah lewat model', function () {
    imporHonor($this->k, [barisHonor(4)]);
    $rekap = RekapHonorEksternal::firstOrFail();

    expect(auth()->user())->toBeNull()
        ->and($this->k->keu->can('update', $rekap))->toBeFalse()
        ->and($this->k->keu->can('batalkan', $rekap))->toBeTrue()
        ->and($this->k->adminPmat->can('batalkan', $rekap))->toBeFalse();
});
