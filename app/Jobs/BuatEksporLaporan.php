<?php

namespace App\Jobs;

use App\Enums\StatusEkspor;
use App\Laporan\KatalogLaporan;
use App\Laporan\PenulisLaporan;
use App\Models\LaporanEkspor;
use App\Notifications\EksporGagal;
use App\Notifications\EksporSiap;
use App\Support\Pengaturan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyusun ekspor laporan besar ke storage/app/tmp/ekspor (SEMENTARA, ≤ 24 jam).
 */
class BuatEksporLaporan implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public LaporanEkspor $ekspor)
    {
        $this->onQueue('ekspor');
    }

    public function handle(): void
    {
        $ekspor = $this->ekspor->fresh() ?? $this->ekspor;
        $ekspor->forceFill(['status' => StatusEkspor::Diproses, 'mulai_pada' => now()])->save();

        try {
            $data = KatalogLaporan::bangun($ekspor->jenis, $ekspor->parameter, $ekspor->peminta);
            $relatif = "tmp/ekspor/{$ekspor->id}.{$ekspor->format}";
            $jalur = storage_path('app/'.$relatif);
            File::ensureDirectoryExists(dirname($jalur));

            if ($ekspor->format === 'pdf') {
                PenulisLaporan::pdf($data, $ekspor->peminta)->save($jalur);
            } else {
                PenulisLaporan::tulisXlsx($data, $jalur);
            }

            $jam = min(24, max(1, (int) Pengaturan::get('masa_berlaku_ekspor_jam', 24)));

            $ekspor->forceFill([
                'status' => StatusEkspor::Selesai,
                'path' => $relatif,
                'ukuran_byte' => (int) filesize($jalur),
                'selesai_pada' => now(),
                'kedaluwarsa_pada' => now()->addHours($jam),
            ])->save();

            $ekspor->peminta->notify(new EksporSiap($ekspor));
        } catch (Throwable $e) {
            $ekspor->forceFill(['status' => StatusEkspor::Gagal, 'selesai_pada' => now(), 'pesan_galat' => Str::limit($e->getMessage(), 250)])->save();
            $ekspor->peminta->notify(new EksporGagal($ekspor));
        }
    }
}
