<?php

namespace App\Console\Commands;

use App\Jobs\PeriksaTautanBerkas;
use App\Models\TautanBerkas;
use App\Models\TautanBerkasBeku;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Periksa ulang mingguan tautan aktif yang belum/lama tidak dicek; tautan yang sudah dibekukan dilewati
 * (acuannya snapshot beku).
 */
#[Signature('keuangan:periksa-ulang-tautan {--dry-run : Hitung tanpa mengantrekan}')]
#[Description('Antrekan pemeriksaan ulang keteraksesan tautan berkas')]
class PeriksaUlangTautan extends Command
{
    public function handle(): int
    {
        $query = TautanBerkas::query()
            ->where(fn ($q) => $q->whereNull('dicek_pada')->orWhere('dicek_pada', '<', now()->subDays(7)))
            ->whereNotIn('id', TautanBerkasBeku::query()->select('tautan_berkas_id'));

        $jumlah = 0;

        foreach ($query->lazyById(200) as $tautan) {
            $jumlah++;

            if (! $this->option('dry-run')) {
                PeriksaTautanBerkas::dispatch($tautan);
            }
        }

        $this->info(($this->option('dry-run') ? '[dry-run] ' : '')."{$jumlah} tautan diantrekan untuk diperiksa.");

        return self::SUCCESS;
    }
}
