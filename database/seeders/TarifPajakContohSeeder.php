<?php

namespace Database\Seeders;

use App\Models\JenisPajak;
use App\Models\TarifPajak;
use Illuminate\Database\Seeder;

/**
 * Baris tarif referensi CONTOH. Persentase sengaja 0,00 (belum diisi) karena angka tarif wajib
 * diisi dan dikonfirmasi bagian keuangan (ADR 0002 parameter 7); dasar hukum dikosongkan.
 */
class TarifPajakContohSeeder extends Seeder
{
    public const KETERANGAN = 'CONTOH — angka wajib dikonfirmasi bagian keuangan';

    public function run(): void
    {
        $daftar = [
            'pph21' => 'Honorarium (berjenjang menurut golongan/status)',
            'pph22' => 'Pembelian barang oleh bendahara',
            'pph23' => 'Jasa',
            'ppn' => 'Pembelian barang/jasa kena pajak',
        ];

        $mulai = now()->startOfYear()->toDateString();

        foreach ($daftar as $kode => $uraian) {
            $jenis = JenisPajak::where('kode', $kode)->first();

            if ($jenis === null || TarifPajak::where('jenis_pajak_id', $jenis->id)->where('uraian', $uraian)->exists()) {
                continue;
            }

            TarifPajak::create([
                'jenis_pajak_id' => $jenis->id,
                'uraian' => $uraian,
                'tarif_persen' => '0.00',
                'berlaku_mulai' => $mulai,
                'dasar_hukum' => null,
                'perlu_verifikasi' => true,
                'keterangan' => self::KETERANGAN,
            ]);
        }
    }
}
