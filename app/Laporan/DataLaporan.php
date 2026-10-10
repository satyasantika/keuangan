<?php

namespace App\Laporan;

/**
 * Isi laporan siap ditulis ke xlsx/pdf/pratinjau. Nilai uang tetap string desimal.
 */
final readonly class DataLaporan
{
    /**
     * @param  list<string>  $kolom
     * @param  list<list<string|int|null>>  $baris
     * @param  list<string|int|null>|null  $total
     * @param  list<int>  $kolomUang  indeks kolom bernilai uang
     * @param  list<string>  $catatan
     */
    public function __construct(
        public string $kode,
        public string $judul,
        public string $subjudul,
        public array $kolom,
        public array $baris,
        public ?array $total = null,
        public array $kolomUang = [],
        public array $catatan = [],
    ) {}

    public function namaBerkas(string $format): string
    {
        return strtolower($this->kode).'-'.now()->format('Ymd-His').'.'.$format;
    }
}
