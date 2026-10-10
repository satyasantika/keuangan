<?php

namespace App\Actions\Revisi;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Enums\StatusRab;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PaguTidakCukupException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan revisi draf setelah validasi BR-11 dan BR-04 (kenaikan terhadap sisa pagu).
 */
class AjukanRevisiRab
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly HitungSisaPagu $hitungSisaPagu,
    ) {}

    public function handle(Rab $rab, User $oleh): Rab
    {
        if (! $oleh->can('ajukan', $rab)) {
            throw new AuthorizationException('Revisi hanya dapat diajukan unitnya selama berstatus draf.');
        }

        $pengajuan = $rab->pengajuan;
        $tahun = (int) $pengajuan->tahunAnggaran()->value('tahun');

        return DenganKunci::jalankan("aksi:rab:{$rab->id}", fn (): Rab => DenganKunci::jalankan("pagu:{$tahun}:{$pengajuan->unit_id}", fn (): Rab => DB::transaction(function () use ($rab, $oleh): Rab {
            $terkini = Rab::query()->lockForUpdate()->findOrFail($rab->getKey());

            if ($terkini->status !== StatusRab::Draf || $terkini->nomor_revisi === 0) {
                throw new TransisiStatusTidakSahException('Hanya revisi berstatus draf yang dapat diajukan.');
            }

            self::validasiRevisi($terkini, $this->hitungSisaPagu);

            $terkini->forceFill(['status' => StatusRab::Diajukan, 'diajukan_pada' => now()])->save();
            $this->catatRiwayat->handle($terkini, StatusRab::Draf, StatusRab::Diajukan, $oleh, "Revisi ke-{$terkini->nomor_revisi} diajukan");

            return $terkini;
        })));
    }

    /**
     * BR-11 & BR-04 — dipakai saat diajukan dan dicek ulang saat aktivasi.
     */
    public static function validasiRevisi(Rab $revisi, HitungSisaPagu $hitungSisaPagu): void
    {
        $pengajuan = Pengajuan::query()->findOrFail($revisi->pengajuan_id);
        $itemRevisi = RabItem::query()->where('rab_id', $revisi->id)->get();
        $itemAktif = RabItem::query()->where('rab_id', $pengajuan->rab_aktif_id)->get();

        foreach ($itemAktif as $asal) {
            $realisasi = Uang::jumlahkan(RealisasiItem::query()->where('rab_item_id', $asal->id)->pluck('jumlah_bruto')->map(fn ($j): string => (string) $j)->all());

            if (Uang::bandingkan($realisasi, '0') === 0) {
                continue;
            }

            $turunan = $itemRevisi->firstWhere('asal_rab_item_id', $asal->id);

            if ($turunan === null) {
                throw new KesalahanDomain("Item \"{$asal->uraian}\" sudah memiliki realisasi dan harus tetap ada di revisi.");
            }

            if (Uang::bandingkan((string) $turunan->jumlah, $realisasi) < 0) {
                throw new KesalahanDomain("Jumlah item \"{$asal->uraian}\" tidak boleh kurang dari realisasinya (".Uang::format($realisasi).').');
            }
        }

        $totalRealisasi = (string) $pengajuan->total_realisasi;

        if (Uang::bandingkan((string) $revisi->total, $totalRealisasi) < 0) {
            throw new KesalahanDomain('Total revisi tidak boleh kurang dari total realisasi ('.Uang::format($totalRealisasi).').');
        }

        $totalAktif = (string) RabItem::query()->where('rab_id', $pengajuan->rab_aktif_id)->get()->pipe(fn ($c) => Uang::jumlahkan($c->pluck('jumlah')->map(fn ($j): string => (string) $j)->all()));
        $kenaikan = Uang::kurang((string) $revisi->total, $totalAktif);

        if (Uang::bandingkan($kenaikan, '0') > 0) {
            $sisa = $hitungSisaPagu->handle($pengajuan->tahun_anggaran_id, $pengajuan->unit_id);

            if ($sisa === null || Uang::bandingkan($kenaikan, $sisa['sisa']) > 0) {
                throw new PaguTidakCukupException('Kenaikan revisi '.Uang::format($kenaikan).' melebihi sisa pagu unit '.Uang::format($sisa['sisa'] ?? '0').'.');
            }
        }
    }

    /**
     * Peringatan non-blokir: total revisi di bawah dana yang sudah dicairkan menjadi sisa dana.
     *
     * @return list<string>
     */
    public static function peringatan(Rab $revisi): array
    {
        $pengajuan = $revisi->pengajuan;

        if (Uang::bandingkan((string) $revisi->total, (string) $pengajuan->total_dicairkan) < 0) {
            return ['Total revisi lebih kecil dari dana yang sudah dicairkan; selisih '.Uang::format(Uang::kurang((string) $pengajuan->total_dicairkan, (string) $revisi->total)).' akan menjadi sisa dana yang wajib dikembalikan.'];
        }

        return [];
    }
}
