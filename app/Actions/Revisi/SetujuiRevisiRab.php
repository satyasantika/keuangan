<?php

namespace App\Actions\Revisi;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\User;
use App\Policies\RabPolicy;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Wadek menyetujui revisi (diverifikasi_keuangan → disetujui) lalu mengaktifkannya (BR-10, BR-11).
 */
class SetujuiRevisiRab
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly HitungSisaPagu $hitungSisaPagu,
        private readonly HitungUlangTotalPengajuan $hitungUlang,
    ) {}

    public function handle(Rab $rab, User $oleh, ?string $catatan = null): Rab
    {
        if (! $oleh->can('setujui', $rab)) {
            if ($oleh->can('revisi.setujui') && (new RabPolicy)->melanggarPemisahan($oleh, $rab, TahapPersetujuan::Wadek)) {
                throw new PelanggaranPemisahanTugasException('Pemisahan tugas: pembuat revisi atau pengguna yang sudah bertindak di tahap lain tidak boleh menyetujui revisi ini.');
            }

            throw new AuthorizationException('Anda tidak berhak menyetujui revisi ini.');
        }

        return $this->dalamKunci($rab, function (Rab $terkini) use ($oleh, $catatan): Rab {
            if ($terkini->status !== StatusRab::DiverifikasiKeuangan || ! $oleh->can('setujui', $terkini)) {
                throw new TransisiStatusTidakSahException('Revisi sudah diproses pengguna lain.');
            }

            return $this->aktifkan($terkini, $oleh, TahapPersetujuan::Wadek, $catatan);
        });
    }

    /**
     * Lock revisi → dana pengajuan → pagu unit, lalu transaksi.
     *
     * @param  callable(Rab): Rab  $fn
     */
    public function dalamKunci(Rab $rab, callable $fn): Rab
    {
        $pengajuan = $rab->pengajuan;
        $tahun = (int) $pengajuan->tahunAnggaran()->value('tahun');

        return DenganKunci::jalankan("aksi:rab:{$rab->id}", fn (): Rab => DenganKunci::jalankan(
            "aksi:pengajuan-dana:{$pengajuan->id}",
            fn (): Rab => DenganKunci::jalankan("pagu:{$tahun}:{$pengajuan->unit_id}", fn (): Rab => DB::transaction(
                fn (): Rab => $fn(Rab::query()->lockForUpdate()->findOrFail($rab->getKey())),
            )),
        ));
    }

    /**
     * Aktivasi versi: cek ulang BR-04/BR-11, versi lama digantikan, realisasi dipetakan ke item baru.
     */
    public function aktifkan(Rab $revisi, User $oleh, TahapPersetujuan $tahap, ?string $catatan): Rab
    {
        $pengajuan = Pengajuan::query()->lockForUpdate()->findOrFail($revisi->pengajuan_id);

        AjukanRevisiRab::validasiRevisi($revisi, $this->hitungSisaPagu);

        $lama = Rab::query()->lockForUpdate()->findOrFail($pengajuan->rab_aktif_id);
        $lama->forceFill(['status' => StatusRab::Digantikan])->save();
        $this->catatRiwayat->handle($lama, StatusRab::Disetujui, StatusRab::Digantikan, $oleh, "Digantikan revisi ke-{$revisi->nomor_revisi}", $tahap);

        $dari = $revisi->status;
        $revisi->forceFill(['status' => StatusRab::Disetujui, 'disetujui_oleh' => $oleh->getKey(), 'disetujui_pada' => now()])->save();
        $this->catatRiwayat->handle($revisi, $dari, StatusRab::Disetujui, $oleh, $catatan, $tahap);

        foreach (RabItem::query()->where('rab_id', $revisi->id)->whereNotNull('asal_rab_item_id')->get() as $item) {
            RealisasiItem::petakanTanpaPenjaga((string) $item->asal_rab_item_id, $item->id);
        }

        $pengajuan->forceFill(['rab_aktif_id' => $revisi->id, 'total_disetujui' => $revisi->total])->save();
        $this->catatRiwayat->handle($pengajuan, $pengajuan->status, $pengajuan->status, $oleh, "Revisi RAB ke-{$revisi->nomor_revisi} berlaku", $tahap);

        $this->hitungUlang->handle($pengajuan);

        return $revisi;
    }
}
