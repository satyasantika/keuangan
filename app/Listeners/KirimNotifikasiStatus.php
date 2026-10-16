<?php

namespace App\Listeners;

use App\Enums\Peran;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPengembalian;
use App\Enums\StatusPeriode;
use App\Enums\StatusRab;
use App\Enums\StatusSpj;
use App\Enums\TahapPersetujuan;
use App\Events\StatusBerubah;
use App\Models\Pencairan;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\Periode;
use App\Models\Rab;
use App\Models\Spj;
use App\Models\User;
use App\Notifications\NotifikasiKeuangan;
use App\Notifications\PencairanDicatat;
use App\Notifications\PengajuanDikembalikan;
use App\Notifications\PengajuanDisetujui;
use App\Notifications\PengajuanDitolak;
use App\Notifications\PengajuanMenungguTindakan;
use App\Notifications\PengajuanSelesai;
use App\Notifications\PengembalianMenungguVerifikasi;
use App\Notifications\PeriodeDibukaKembali;
use App\Notifications\PeriodeDikunci;
use App\Notifications\RevisiRabDiputuskan;
use App\Notifications\RevisiRabMenungguTindakan;
use App\Notifications\SpjDiputuskan;
use App\Notifications\SpjMenungguVerifikasi;
use App\Policies\SpjPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Pemetaan perubahan status → notifikasi. Pengguna nonaktif tidak menerima notifikasi.
 */
class KirimNotifikasiStatus implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(StatusBerubah $event): void
    {
        $model = $event->model;
        $ke = $event->ke;

        if ($event->dari === $ke) {
            return; // catatan tanpa perubahan status (mis. revisi berlaku, perubahan pagu)
        }

        match (true) {
            $model instanceof Pengajuan && $ke instanceof StatusPengajuan => $this->pengajuan($model, $ke, $event),
            $model instanceof Pencairan && $ke === StatusPencairan::Dicatat => $this->kirim(
                $this->unit($model->pengajuan->unit_id, [Peran::AdminUnit, Peran::KepalaUnit]),
                new PencairanDicatat($model),
            ),
            $model instanceof Rab && $ke instanceof StatusRab && $model->nomor_revisi > 0 => $this->revisi($model, $ke, $event),
            $model instanceof Spj && $ke instanceof StatusSpj => $this->spj($model, $ke, $event),
            $model instanceof Pengembalian && $ke === StatusPengembalian::Diajukan => $this->kirim(
                $this->peran([Peran::Bpp])->reject(fn (User $u) => $u->getKey() === $model->dibuat_oleh),
                new PengembalianMenungguVerifikasi($model),
            ),
            $model instanceof Periode && $ke instanceof StatusPeriode => $this->kirim(
                $this->peran([Peran::AdminKeuangan, Peran::Bpp, Peran::WadekKeuangan]),
                $ke === StatusPeriode::Terkunci ? new PeriodeDikunci($model) : new PeriodeDibukaKembali($model, $event->catatan),
            ),
            default => null,
        };
    }

    private function pengajuan(Pengajuan $pengajuan, StatusPengajuan $ke, StatusBerubah $event): void
    {
        switch ($ke) {
            case StatusPengajuan::Diajukan:
            case StatusPengajuan::DiverifikasiUnit:
            case StatusPengajuan::DiverifikasiKeuangan:
                $tahap = $pengajuan->tahapBerjalan();

                if ($tahap !== null) {
                    $this->kirim(
                        $this->pemegangTahap($pengajuan, $tahap)->reject(fn (User $u) => $pengajuan->melanggarPemisahanTugas($u, $tahap)),
                        new PengajuanMenungguTindakan($pengajuan, $tahap),
                    );
                }
                break;
            case StatusPengajuan::Dikembalikan:
            case StatusPengajuan::Ditolak:
                $penerima = $this->unit($pengajuan->unit_id, [Peran::AdminUnit])->merge(User::query()->whereKey($pengajuan->dibuat_oleh)->where('aktif', true)->get());
                $this->kirim($penerima, $ke === StatusPengajuan::Dikembalikan
                    ? new PengajuanDikembalikan($pengajuan, $event->catatan)
                    : new PengajuanDitolak($pengajuan, $event->catatan));
                break;
            case StatusPengajuan::Disetujui:
                if ($event->dari !== StatusPengajuan::Dicairkan) {
                    $this->kirim(
                        $this->unit($pengajuan->unit_id, [Peran::AdminUnit, Peran::KepalaUnit])->merge($this->peran([Peran::Bpp])),
                        new PengajuanDisetujui($pengajuan),
                    );
                }
                break;
            case StatusPengajuan::Selesai:
                $this->kirim($this->unit($pengajuan->unit_id, [Peran::AdminUnit, Peran::KepalaUnit]), new PengajuanSelesai($pengajuan));
                break;
            default:
                break;
        }
    }

    private function revisi(Rab $rab, StatusRab $ke, StatusBerubah $event): void
    {
        $penyusun = $this->unit($rab->pengajuan->unit_id, [Peran::AdminUnit])
            ->merge(User::query()->whereKey($rab->dibuat_oleh)->where('aktif', true)->get());

        match ($ke) {
            StatusRab::Diajukan => $this->kirim($this->peran([Peran::AdminKeuangan])->reject(fn (User $u) => $u->getKey() === $rab->dibuat_oleh), new RevisiRabMenungguTindakan($rab)),
            StatusRab::DiverifikasiKeuangan => $this->kirim($this->peran([Peran::WadekKeuangan])->reject(fn (User $u) => $u->getKey() === $rab->dibuat_oleh), new RevisiRabMenungguTindakan($rab)),
            StatusRab::Disetujui, StatusRab::Ditolak => $this->kirim($penyusun, new RevisiRabDiputuskan($rab, $event->catatan)),
            StatusRab::Draf => $event->dari === null ? null : $this->kirim($penyusun, new RevisiRabDiputuskan($rab, $event->catatan)),
            default => null,
        };
    }

    private function spj(Spj $spj, StatusSpj $ke, StatusBerubah $event): void
    {
        if ($ke === StatusSpj::Diajukan) {
            $this->kirim(
                $this->peran([Peran::AdminKeuangan, Peran::Bpp])->reject(fn (User $u) => app(SpjPolicy::class)->terlibatMenyusun($u, $spj)),
                new SpjMenungguVerifikasi($spj),
            );

            return;
        }

        if (in_array($ke, [StatusSpj::Diterima, StatusSpj::Dikembalikan], true)) {
            $this->kirim(
                $this->unit($spj->pengajuan->unit_id, [Peran::AdminUnit])->merge(User::query()->whereKey($spj->dibuat_oleh)->where('aktif', true)->get()),
                new SpjDiputuskan($spj, $event->catatan),
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function pemegangTahap(Pengajuan $pengajuan, TahapPersetujuan $tahap): Collection
    {
        return match ($tahap) {
            TahapPersetujuan::KepalaUnit => $this->unit($pengajuan->unit_id, [Peran::KepalaUnit]),
            TahapPersetujuan::Keuangan => $this->peran([Peran::AdminKeuangan]),
            TahapPersetujuan::Wadek => $this->peran([Peran::WadekKeuangan]),
            TahapPersetujuan::Bendahara => collect(),
        };
    }

    /**
     * @param  list<Peran>  $peran
     * @return Collection<int, User>
     */
    private function peran(array $peran): Collection
    {
        return User::query()->where('aktif', true)->role(array_map(fn (Peran $p) => $p->value, $peran))->get();
    }

    /**
     * @param  list<Peran>  $peran
     * @return Collection<int, User>
     */
    private function unit(string $unitId, array $peran): Collection
    {
        return User::query()->where('aktif', true)->where('unit_id', $unitId)
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', array_map(fn (Peran $p) => $p->value, $peran)))
            ->get();
    }

    /**
     * @param  Collection<int, User>  $penerima
     */
    private function kirim(Collection $penerima, NotifikasiKeuangan $notifikasi): void
    {
        $penerima = $penerima->where('aktif', true)->unique('id')->values();

        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, $notifikasi);
        }
    }
}
