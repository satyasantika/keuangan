<?php

namespace App\Actions\Master;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Models\Periode;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BuatTahunAnggaran
{
    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(int $tahun, User $oleh, ?string $keterangan = null): TahunAnggaran
    {
        if (! $oleh->can('create', TahunAnggaran::class)) {
            throw new AuthorizationException('Anda tidak berhak membuat tahun anggaran.');
        }

        if ($tahun < 2000 || $tahun > 2100) {
            throw new KesalahanDomain('Tahun anggaran harus antara 2000 dan 2100.');
        }

        return DenganKunci::jalankan("aksi:tahun-anggaran:{$tahun}", function () use ($tahun, $oleh, $keterangan): TahunAnggaran {
            return DB::transaction(function () use ($tahun, $oleh, $keterangan): TahunAnggaran {
                if (TahunAnggaran::withTrashed()->where('tahun', $tahun)->exists()) {
                    throw new KesalahanDomain("Tahun anggaran {$tahun} sudah ada.");
                }

                $tahunAnggaran = new TahunAnggaran([
                    'tahun' => $tahun,
                    'nama' => "Tahun Anggaran {$tahun}",
                    'tanggal_mulai' => "{$tahun}-01-01",
                    'tanggal_selesai' => "{$tahun}-12-31",
                    'keterangan' => $keterangan,
                ]);
                $tahunAnggaran->forceFill(['status' => StatusTahunAnggaran::Perencanaan])->save();

                for ($bulan = 1; $bulan <= 12; $bulan++) {
                    $awal = Carbon::create($tahun, $bulan, 1)->locale('id');

                    $periode = new Periode([
                        'tahun_anggaran_id' => $tahunAnggaran->id,
                        'bulan' => $bulan,
                        'nama' => $awal->translatedFormat('F Y'),
                        'tanggal_mulai' => $awal->toDateString(),
                        'tanggal_selesai' => $awal->copy()->endOfMonth()->toDateString(),
                    ]);
                    $periode->save();
                }

                $this->catatRiwayat->handle($tahunAnggaran, null, StatusTahunAnggaran::Perencanaan, $oleh, 'Tahun anggaran dibuat.');

                return $tahunAnggaran;
            });
        });
    }
}
