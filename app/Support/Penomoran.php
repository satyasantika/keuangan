<?php

namespace App\Support;

use App\Models\Penomoran as NomorTerakhir;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran berurutan tanpa celah ganda (BR-05): lock cache (database) + lockForUpdate.
 */
final class Penomoran
{
    /**
     * @param  string  $format  mis. "PGJ/{tahun}/{urut}"; {urut} berisi 4 digit zero-pad
     */
    public static function berikutnya(string $jenis, int $tahun, string $format): string
    {
        return DenganKunci::jalankan("penomoran:{$jenis}:{$tahun}", function () use ($jenis, $tahun, $format): string {
            return DB::transaction(function () use ($jenis, $tahun, $format): string {
                NomorTerakhir::firstOrCreate(['jenis' => $jenis, 'tahun' => $tahun]);

                $baris = NomorTerakhir::where('jenis', $jenis)->where('tahun', $tahun)->lockForUpdate()->firstOrFail();
                $baris->nomor_terakhir++;
                $baris->save();

                return strtr($format, [
                    '{tahun}' => (string) $tahun,
                    '{urut}' => str_pad((string) $baris->nomor_terakhir, 4, '0', STR_PAD_LEFT),
                ]);
            });
        });
    }
}
