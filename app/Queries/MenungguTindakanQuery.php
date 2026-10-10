<?php

namespace App\Queries;

use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pengajuan yang menunggu tindakan pengguna (US-STJ-04), tanpa yang melanggar pemisahan tugas (BR-07).
 */
class MenungguTindakanQuery
{
    /**
     * @return Builder<Pengajuan>
     */
    public static function untuk(User $pengguna): Builder
    {
        $query = Pengajuan::query()->with('unit')->where('dibuat_oleh', '!=', $pengguna->getKey());

        if (! $pengguna->can('persetujuan.setujui')) {
            return $query->whereRaw('1 = 0');
        }

        $klausa = [];

        if ($pengguna->hasRole(Peran::KepalaUnit->value) && $pengguna->unit_id !== null) {
            $klausa[] = [TahapPersetujuan::KepalaUnit, fn (Builder $q) => $q->where('status', StatusPengajuan::Diajukan->value)
                ->where('wajib_kepala_unit', true)
                ->where('unit_id', $pengguna->unit_id)];
        }

        if ($pengguna->hasRole(Peran::AdminKeuangan->value)) {
            $klausa[] = [TahapPersetujuan::Keuangan, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('status', StatusPengajuan::DiverifikasiUnit->value)
                ->orWhere(fn (Builder $x) => $x->where('status', StatusPengajuan::Diajukan->value)->where('wajib_kepala_unit', false)))];
        }

        if ($pengguna->hasRole(Peran::WadekKeuangan->value)) {
            $klausa[] = [TahapPersetujuan::Wadek, fn (Builder $q) => $q->where('status', StatusPengajuan::DiverifikasiKeuangan->value)];
        }

        if ($klausa === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($klausa, $pengguna): void {
            foreach ($klausa as [$tahap, $syarat]) {
                $q->orWhere(fn (Builder $w) => $syarat($w)->whereNotExists(fn ($r) => $r->selectRaw('1')
                    ->from('riwayat_status')
                    ->whereColumn('riwayat_status.model_id', 'pengajuan.id')
                    ->where('riwayat_status.model_type', 'pengajuan')
                    ->where('riwayat_status.user_id', $pengguna->getKey())
                    ->whereNotNull('riwayat_status.tahap')
                    ->where('riwayat_status.tahap', '!=', $tahap->value)));
            }
        });
    }

    /**
     * Revisi RAB yang menunggu tindakan pengguna (verifikasi keuangan / persetujuan wadek), tanpa pelanggaran BR-07.
     *
     * @return Builder<Rab>
     */
    public static function revisiUntuk(User $pengguna): Builder
    {
        $query = Rab::query()->with('pengajuan.unit')->where('nomor_revisi', '>', 0)->where('dibuat_oleh', '!=', $pengguna->getKey());
        $klausa = [];

        if ($pengguna->can('revisi.setujui') && $pengguna->hasRole(Peran::AdminKeuangan->value)) {
            $klausa[] = [TahapPersetujuan::Keuangan, StatusRab::Diajukan];
        }

        if ($pengguna->can('revisi.setujui') && $pengguna->hasRole(Peran::WadekKeuangan->value)) {
            $klausa[] = [TahapPersetujuan::Wadek, StatusRab::DiverifikasiKeuangan];
        }

        if ($klausa === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($klausa, $pengguna): void {
            foreach ($klausa as [$tahap, $status]) {
                $q->orWhere(fn (Builder $w) => $w->where('status', $status->value)->whereNotExists(fn ($r) => $r->selectRaw('1')
                    ->from('riwayat_status')
                    ->whereColumn('riwayat_status.model_id', 'rab.id')
                    ->where('riwayat_status.model_type', 'rab')
                    ->where('riwayat_status.user_id', $pengguna->getKey())
                    ->whereNotNull('riwayat_status.tahap')
                    ->where('riwayat_status.tahap', '!=', $tahap->value)));
            }
        });
    }

    /**
     * Jumlah pengajuan menunggu per tahap (untuk widget antrean).
     *
     * @return array<string, int>
     */
    public static function jumlahPerTahap(?string $unitId = null): array
    {
        $dasar = fn () => Pengajuan::query()->when($unitId, fn (Builder $q) => $q->where('unit_id', $unitId));

        return [
            TahapPersetujuan::KepalaUnit->value => $dasar()->where('status', StatusPengajuan::Diajukan->value)->where('wajib_kepala_unit', true)->count(),
            TahapPersetujuan::Keuangan->value => $dasar()->where(fn (Builder $w) => $w->where('status', StatusPengajuan::DiverifikasiUnit->value)
                ->orWhere(fn (Builder $x) => $x->where('status', StatusPengajuan::Diajukan->value)->where('wajib_kepala_unit', false)))->count(),
            TahapPersetujuan::Wadek->value => $dasar()->where('status', StatusPengajuan::DiverifikasiKeuangan->value)->count(),
        ];
    }
}
