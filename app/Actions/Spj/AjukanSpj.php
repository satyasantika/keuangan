<?php

namespace App\Actions\Spj;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusRab;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Rab;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Pengaturan;
use App\Support\Penomoran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * draf/dikembalikan → diajukan (BR-17); nomor SPJ terbit pada pengajuan pertama.
 */
class AjukanSpj
{
    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Spj $spj, User $oleh): Spj
    {
        if (! $oleh->can('ajukan', $spj)) {
            throw new AuthorizationException('Anda tidak berhak mengajukan SPJ ini.');
        }

        return DenganKunci::jalankan("aksi:spj:{$spj->id}", fn (): Spj => DB::transaction(function () use ($spj, $oleh): Spj {
            $terkini = Spj::query()->with('pengajuan.tahunAnggaran')->lockForUpdate()->findOrFail($spj->getKey());
            $dari = $terkini->status;

            if (! $dari->bolehBerpindahKe(StatusSpj::Diajukan)) {
                throw new TransisiStatusTidakSahException('Hanya SPJ berstatus draf atau dikembalikan yang dapat diajukan.');
            }

            $realisasi = RealisasiItem::query()->where('spj_id', $terkini->id)->get();

            if ($realisasi->isEmpty()) {
                throw new KesalahanDomain('SPJ wajib memiliki minimal satu realisasi.');
            }

            foreach ($realisasi as $item) {
                if (! $item->tautanJenis(...CatatRealisasi::JENIS_BUKTI_UTAMA)->exists()) {
                    throw new KesalahanDomain("Realisasi \"{$item->uraian}\" belum memiliki tautan kuitansi/nota/faktur.");
                }
            }

            $revisiBerjalan = Rab::query()
                ->where('pengajuan_id', $terkini->pengajuan_id)
                ->where('nomor_revisi', '>', 0)
                ->whereIn('status', [StatusRab::Draf->value, StatusRab::Diajukan->value, StatusRab::DiverifikasiKeuangan->value])
                ->exists();

            if ($revisiBerjalan) {
                throw new KesalahanDomain('Masih ada revisi RAB yang berjalan; SPJ belum dapat diajukan (BR-10).');
            }

            $nomorBaru = $terkini->nomor === null;
            $tahun = (int) $terkini->pengajuan->tahunAnggaran->tahun;

            $terkini->forceFill([
                'nomor' => $terkini->nomor ?? Penomoran::berikutnya('spj', $tahun, (string) Pengaturan::get('format_nomor_spj', 'SPJ/{tahun}/{urut}')),
                'status' => StatusSpj::Diajukan,
                'diajukan_pada' => now(),
            ])->save();

            $this->catatRiwayat->handle($terkini, $dari, StatusSpj::Diajukan, $oleh, $nomorBaru ? 'Diajukan' : 'Diajukan ulang');

            return $terkini;
        }));
    }
}
