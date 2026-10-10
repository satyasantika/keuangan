<?php

namespace App\Console\Commands;

use App\Enums\Peran;
use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\User;
use App\Notifications\PengingatKunciPeriode;
use App\Support\Pengaturan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Dijadwalkan harian; hanya bertindak pada tanggal pengingat_kunci (pengaturan).
 */
#[Signature('keuangan:pengingat-kunci-periode')]
#[Description('Ingatkan admin-keuangan mengunci periode bulan lalu')]
class KirimPengingatKunciPeriode extends Command
{
    public function handle(): int
    {
        if ((int) now()->day !== (int) Pengaturan::get('tanggal_pengingat_kunci', 5)) {
            $this->info('Bukan tanggal pengingat kunci periode.');

            return self::SUCCESS;
        }

        $bulanLalu = now()->subMonthNoOverflow();
        $periode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $bulanLalu->toDateString())
            ->whereDate('tanggal_selesai', '>=', $bulanLalu->toDateString())
            ->where('status', StatusPeriode::Terbuka->value)
            ->first();

        if ($periode === null) {
            $this->info('Periode bulan lalu sudah terkunci atau tidak ada.');

            return self::SUCCESS;
        }

        $penerima = User::query()->where('aktif', true)->role(Peran::AdminKeuangan->value)->get();

        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new PengingatKunciPeriode($periode));
        }

        $this->info("Pengingat kunci {$periode->nama} dikirim.");

        return self::SUCCESS;
    }
}
