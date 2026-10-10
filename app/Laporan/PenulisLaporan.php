<?php

namespace App\Laporan;

use App\Models\User;
use App\Support\Pengaturan;
use App\Support\Uang;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Menulis DataLaporan ke xlsx (spatie/simple-excel) atau pdf (dompdf). Keluaran tidak disimpan permanen.
 */
class PenulisLaporan
{
    public static function tulisXlsx(DataLaporan $data, string $jalur): void
    {
        $penulis = SimpleExcelWriter::create($jalur, 'xlsx');
        $penulis->addHeader($data->kolom);

        foreach ($data->baris as $baris) {
            $penulis->addRow(self::barisExcel($data, $baris));
        }

        if ($data->total !== null) {
            $penulis->addRow(self::barisExcel($data, $data->total));
        }

        foreach ([$data->judul.' — '.$data->subjudul, ...$data->catatan] as $catatan) {
            $penulis->addRow(array_combine($data->kolom, [$catatan, ...array_fill(0, count($data->kolom) - 1, '')]));
        }

        $penulis->close();
    }

    public static function pdf(DataLaporan $data, User $pencetak): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.laporan', [
            'data' => $data,
            'pencetak' => $pencetak->name,
            'namaFakultas' => (string) Pengaturan::get('nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan'),
            'dicetakPada' => now()->locale('id'),
            'format' => fn ($nilai, int $indeks): string => in_array($indeks, $data->kolomUang, true) && $nilai !== null && $nilai !== ''
                ? Uang::format((string) $nilai)
                : (string) $nilai,
        ])->setPaper('a4', 'landscape');
    }

    /**
     * Angka uang ditulis sebagai numerik (2 desimal) untuk Excel; nilai lain apa adanya.
     *
     * @param  list<string|int|null>  $baris
     * @return array<string, string|int|float|null>
     */
    private static function barisExcel(DataLaporan $data, array $baris): array
    {
        $hasil = [];

        foreach ($data->kolom as $i => $kolom) {
            $nilai = $baris[$i] ?? null;
            $hasil[$kolom] = in_array($i, $data->kolomUang, true) && is_string($nilai) && is_numeric($nilai)
                ? round((float) $nilai, 2)
                : $nilai;
        }

        return $hasil;
    }
}
