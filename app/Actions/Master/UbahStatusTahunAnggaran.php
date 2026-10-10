<?php

namespace App\Actions\Master;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPeriode;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\Periode;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * perencanaan → berjalan (admin-keuangan) → ditutup (wadek-keuangan, BR-20).
 */
class UbahStatusTahunAnggaran
{
    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(TahunAnggaran $tahunAnggaran, StatusTahunAnggaran $ke, User $oleh, ?string $catatan = null, bool $konfirmasi = false): TahunAnggaran
    {
        $kemampuan = $ke === StatusTahunAnggaran::Ditutup ? 'tutup' : 'update';

        if (! $oleh->can($kemampuan, $tahunAnggaran)) {
            throw new AuthorizationException($ke === StatusTahunAnggaran::Ditutup
                ? 'Hanya wakil dekan keuangan yang dapat menutup tahun anggaran.'
                : 'Anda tidak berhak mengubah status tahun anggaran.');
        }

        return DenganKunci::jalankan("aksi:tahun-anggaran:{$tahunAnggaran->id}", function () use ($tahunAnggaran, $ke, $oleh, $catatan, $konfirmasi): TahunAnggaran {
            return DB::transaction(function () use ($tahunAnggaran, $ke, $oleh, $catatan, $konfirmasi): TahunAnggaran {
                $terkini = TahunAnggaran::query()->lockForUpdate()->findOrFail($tahunAnggaran->getKey());
                $dari = $terkini->status;

                if (! $dari->bolehBerpindahKe($ke)) {
                    throw new TransisiStatusTidakSahException("Status tahun anggaran tidak dapat diubah dari {$dari->getLabel()} ke {$ke->getLabel()}.");
                }

                if ($ke === StatusTahunAnggaran::Ditutup) {
                    self::pastikanSiapDitutup($terkini, $konfirmasi);
                }

                $terkini->forceFill(['status' => $ke])->save();
                $this->catatRiwayat->handle($terkini, $dari, $ke, $oleh, $catatan);

                return $terkini;
            });
        });
    }

    /**
     * Semua periode terkunci; tidak ada pengajuan dalam proses persetujuan; pengajuan dicairkan yang belum
     * selesai harus dikonfirmasi.
     */
    public static function pastikanSiapDitutup(TahunAnggaran $tahun, bool $konfirmasi): void
    {
        if (Periode::query()->where('tahun_anggaran_id', $tahun->id)->where('status', '!=', StatusPeriode::Terkunci->value)->exists()) {
            throw new KesalahanDomain('Semua 12 periode harus terkunci sebelum tahun anggaran ditutup.');
        }

        $dalamProses = Pengajuan::query()->where('tahun_anggaran_id', $tahun->id)
            ->whereIn('status', [StatusPengajuan::Diajukan->value, StatusPengajuan::DiverifikasiUnit->value, StatusPengajuan::DiverifikasiKeuangan->value])
            ->count();

        if ($dalamProses > 0) {
            throw new KesalahanDomain("Masih ada {$dalamProses} pengajuan dalam proses persetujuan.");
        }

        $belumSelesai = self::jumlahDicairkanBelumSelesai($tahun);

        if ($belumSelesai > 0 && ! $konfirmasi) {
            throw new KesalahanDomain("Ada {$belumSelesai} pengajuan dicairkan yang belum selesai; konfirmasi untuk tetap menutup tahun.");
        }
    }

    public static function jumlahDicairkanBelumSelesai(TahunAnggaran $tahun): int
    {
        return Pengajuan::query()->where('tahun_anggaran_id', $tahun->id)->where('status', StatusPengajuan::Dicairkan->value)->count();
    }
}
