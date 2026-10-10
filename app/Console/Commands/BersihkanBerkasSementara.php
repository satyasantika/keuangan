<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus berkas sementara ekspor/impor berumur > 24 jam.
 */
#[Signature('keuangan:bersihkan-sementara {--jam=24 : Umur maksimal berkas}')]
#[Description('Hapus berkas sementara di storage/app/tmp')]
class BersihkanBerkasSementara extends Command
{
    public function handle(): int
    {
        $batas = now()->subHours((int) $this->option('jam'))->getTimestamp();
        $dihapus = [];

        foreach (['ekspor', 'impor'] as $folder) {
            $jalur = storage_path("app/tmp/{$folder}");

            if (! File::isDirectory($jalur)) {
                continue;
            }

            foreach (File::files($jalur) as $berkas) {
                if ($berkas->getMTime() < $batas) {
                    File::delete($berkas->getPathname());
                    $dihapus[] = "tmp/{$folder}/".$berkas->getFilename();
                }
            }
        }

        if ($dihapus !== [] && Schema::hasTable('laporan_ekspor')) {
            DB::table('laporan_ekspor')
                ->whereIn('path', $dihapus)
                ->update(['status' => 'kedaluwarsa', 'path' => null, 'updated_at' => now()]);
        }

        $this->info(count($dihapus).' berkas sementara dihapus.');

        return self::SUCCESS;
    }
}
