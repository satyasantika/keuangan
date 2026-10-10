<?php

namespace App\Actions\Persetujuan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengajuan\HitungSisaPagu;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Exceptions\PaguTidakCukupException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use Illuminate\Support\Facades\DB;

/**
 * Persetujuan akhir: diverifikasi_keuangan → disetujui oleh wadek-keuangan (BR-04 dicek ulang).
 */
class SetujuiPengajuan
{
    use MemeriksaPemisahanTugas;

    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly HitungSisaPagu $hitungSisaPagu,
    ) {}

    public function handle(Pengajuan $pengajuan, User $oleh, ?string $catatan = null): Pengajuan
    {
        $tahun = (int) $pengajuan->tahunAnggaran()->value('tahun');

        return DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", fn (): Pengajuan => DenganKunci::jalankan(
            "pagu:{$tahun}:{$pengajuan->unit_id}",
            fn (): Pengajuan => DB::transaction(function () use ($pengajuan, $oleh, $catatan): Pengajuan {
                $terkini = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());
                $this->pastikanBelumDiproses($pengajuan, $terkini);

                if ($terkini->status !== StatusPengajuan::DiverifikasiKeuangan) {
                    throw new TransisiStatusTidakSahException('Hanya pengajuan yang sudah diverifikasi keuangan yang dapat disetujui.');
                }

                $tahap = $this->pastikanBolehBertindak($terkini, $oleh, TahapPersetujuan::Wadek);

                return $this->setujui($terkini, $oleh, $tahap, $catatan);
            }),
        ));
    }

    /**
     * Logika persetujuan akhir; dipanggil di dalam lock pagu + transaksi (juga oleh VerifikasiPengajuan
     * bila tahap wadek nonaktif).
     */
    public function setujui(Pengajuan $terkini, User $oleh, TahapPersetujuan $tahap, ?string $catatan): Pengajuan
    {
        $dari = $terkini->status;

        if (! $dari->bolehBerpindahKe(StatusPengajuan::Disetujui)) {
            throw new TransisiStatusTidakSahException("Pengajuan berstatus {$dari->getLabel()} tidak dapat disetujui.");
        }

        $sisa = $this->hitungSisaPagu->handle($terkini->tahun_anggaran_id, $terkini->unit_id, kecualiPengajuanId: $terkini->id);
        $rab = $terkini->rabAktif()->lockForUpdate()->firstOrFail();

        if ($sisa === null || Uang::bandingkan((string) $rab->total, $sisa['sisa']) > 0) {
            throw new PaguTidakCukupException('Sisa pagu unit '.Uang::format($sisa['sisa'] ?? '0').' tidak mencukupi total '.Uang::format((string) $rab->total).'.');
        }

        $terkini->forceFill([
            'status' => StatusPengajuan::Disetujui,
            'total_disetujui' => $rab->total,
            'disetujui_pada' => now(),
        ])->save();

        $statusRab = $rab->status;
        $rab->forceFill(['status' => StatusRab::Disetujui, 'disetujui_oleh' => $oleh->getKey(), 'disetujui_pada' => now()])->save();

        $this->catatRiwayat->handle($terkini, $dari, StatusPengajuan::Disetujui, $oleh, $catatan, $tahap);
        $this->catatRiwayat->handle($rab, $statusRab, StatusRab::Disetujui, $oleh, $catatan, $tahap);

        return $terkini;
    }
}
