<?php

use App\Actions\Spj\CatatRealisasi;
use App\Laporan\KatalogLaporan;
use App\Laporan\PenulisLaporan;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Pemeriksaan format ekspor Excel (pengganti migrasi historis sesuai ADR 0001 opsi b): berkas xlsx setiap laporan
 * dapat dibaca ulang dengan header = kolom laporan, kolom uang numerik 2 desimal, dan baris TOTAL = Σ baris.
 */
beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran();
    $p = buatPengajuanDicairkan($this->k);
    [$d, $t] = dataRealisasi($p, '1', '250000');
    app(CatatRealisasi::class)->handle($p, $d, $t, $this->k->adminPmat);
    buatPengajuanDisetujui($this->k, '3', '100000');
});

test('xlsx laporan dapat dibaca ulang dengan header, angka, dan total konsisten', function (string $kode) {
    $data = KatalogLaporan::bangun($kode, ['tahun_anggaran_id' => $this->k->tahun->id, 'jenis_periode' => 'tahunan', 'nilai_periode' => 6], $this->k->keu);
    $jalur = sys_get_temp_dir().'/format-'.Str::uuid7().'.xlsx';

    try {
        PenulisLaporan::tulisXlsx($data, $jalur);
        $pembaca = SimpleExcelReader::create($jalur);
        $baris = $pembaca->getRows()->all();
        expect($pembaca->getHeaders())->toBe($data->kolom);
    } finally {
        @unlink($jalur);
    }

    $dataBaris = array_slice($baris, 0, count($data->baris));

    foreach ($data->kolomUang as $i) {
        $kolom = $data->kolom[$i];

        foreach ($dataBaris as $b) {
            expect($b[$kolom] === '' || is_int($b[$kolom]) || is_float($b[$kolom]))->toBeTrue("{$kode} {$kolom} bukan numerik");
        }

        if ($data->total !== null && $data->total[$i] !== '' && $data->total[$i] !== null) {
            $total = $baris[count($data->baris)];
            $jumlah = array_sum(array_map(fn (array $b) => (float) $b[$kolom], $dataBaris));

            expect(round((float) $total[$kolom], 2))->toBe(round((float) $data->total[$i], 2), "{$kode} total {$kolom}")
                ->and(round($jumlah, 2))->toBe(round((float) $total[$kolom], 2), "{$kode} Σ {$kolom}");
        }
    }
})->with(['LAP-01', 'LAP-02', 'LAP-04', 'LAP-05', 'LAP-06', 'LAP-07', 'LAP-08', 'LAP-09']);
