<?php

namespace App\Console\Commands;

use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusSpj;
use App\Models\Pengajuan;
use App\Models\User;
use App\Notifications\PengingatBatasSpj;
use App\Support\Pengaturan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Pengingat batas SPJ (US-SPJ-04): H-n sesuai pengaturan dan berulang setiap interval saat terlambat.
 */
#[Signature('keuangan:pengingat-spj {--dry-run : Tampilkan daftar tanpa mengirim}')]
#[Description('Kirim pengingat batas SPJ ke unit')]
class KirimPengingatSpj extends Command
{
    public function handle(): int
    {
        $hariIni = now()->startOfDay();
        $hariPengingat = array_map('intval', array_filter(explode(',', (string) Pengaturan::get('hari_pengingat_spj', '3,0')), fn (string $h): bool => trim($h) !== ''));
        $interval = max(1, (int) Pengaturan::get('interval_pengingat_terlambat', 3));
        $baris = [];

        $daftar = Pengajuan::query()
            ->with('unit')
            ->where('status', StatusPengajuan::Dicairkan->value)
            ->whereNotNull('batas_spj')
            ->where(fn (Builder $q) => $q->whereDoesntHave('spj')->orWhereHas('spj', fn (Builder $s) => $s->whereIn('status', [StatusSpj::Draf->value, StatusSpj::Dikembalikan->value])))
            ->get();

        foreach ($daftar as $pengajuan) {
            $sisaHari = (int) $hariIni->diffInDays($pengajuan->batas_spj->copy()->startOfDay(), false);
            $terlambat = $sisaHari < 0;

            $kirim = $terlambat ? abs($sisaHari) % $interval === 0 : in_array($sisaHari, $hariPengingat, true);

            if (! $kirim) {
                continue;
            }

            $penerima = User::query()->where('aktif', true)->where('unit_id', $pengajuan->unit_id)
                ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [Peran::AdminUnit->value, Peran::KepalaUnit->value]))
                ->get();

            if ($terlambat) {
                $penerima = $penerima->merge(User::query()->where('aktif', true)->role(Peran::AdminKeuangan->value)->get());
            }

            $penerima = $penerima->unique('id')->values();
            $baris[] = [$pengajuan->nomor, $pengajuan->unit->kode, $pengajuan->batas_spj->format('d-m-Y'), $sisaHari, $penerima->count()];

            if (! $this->option('dry-run') && $penerima->isNotEmpty()) {
                Notification::send($penerima, new PengingatBatasSpj($pengajuan, $sisaHari));
            }
        }

        $this->table(['Pengajuan', 'Unit', 'Batas SPJ', 'Hari menuju batas', 'Penerima'], $baris);
        $this->info(($this->option('dry-run') ? '[dry-run] ' : '').count($baris).' pengingat.');

        return self::SUCCESS;
    }
}
