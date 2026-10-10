<?php

namespace App\Console\Commands;

use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPengembalian;
use App\Enums\StatusSpj;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\User;
use App\Notifications\RekapMingguan;
use App\Queries\MenungguTindakanQuery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

#[Signature('keuangan:rekap-mingguan')]
#[Description('Kirim rekap mingguan antrean persetujuan, SPJ terlambat, dan pengembalian menunggu')]
class KirimRekapMingguan extends Command
{
    public function handle(): int
    {
        $ringkasan = [
            'menunggu' => MenungguTindakanQuery::jumlahPerTahap(),
            'spj_terlambat' => Pengajuan::query()->with('unit')
                ->where('status', StatusPengajuan::Dicairkan->value)
                ->whereDate('batas_spj', '<', now()->toDateString())
                ->where(fn (Builder $q) => $q->whereDoesntHave('spj')->orWhereHas('spj', fn (Builder $s) => $s->where('status', '!=', StatusSpj::Diterima->value)))
                ->get()
                ->countBy(fn (Pengajuan $p) => $p->unit->kode)
                ->all(),
            'pengembalian_menunggu' => Pengembalian::query()->where('status', StatusPengembalian::Diajukan->value)->count(),
        ];

        $penerima = User::query()->where('aktif', true)->role([Peran::AdminKeuangan->value, Peran::Bpp->value, Peran::WadekKeuangan->value])->get();

        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new RekapMingguan($ringkasan));
        }

        $this->info(RekapMingguan::teks($ringkasan));

        return self::SUCCESS;
    }
}
