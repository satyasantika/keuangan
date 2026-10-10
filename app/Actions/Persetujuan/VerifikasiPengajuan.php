<?php

namespace App\Actions\Persetujuan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengajuan;
use App\Enums\TahapPersetujuan;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi tahap kepala unit atau keuangan (BR-06). Bila tahap wadek nonaktif (snapshot),
 * verifikasi keuangan langsung menjadi persetujuan akhir.
 */
class VerifikasiPengajuan
{
    use MemeriksaPemisahanTugas;

    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly SetujuiPengajuan $setujuiPengajuan,
    ) {}

    public function handle(Pengajuan $pengajuan, User $oleh, ?string $catatan = null): Pengajuan
    {
        $tahun = (int) $pengajuan->tahunAnggaran()->value('tahun');

        return DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", fn (): Pengajuan => DenganKunci::jalankan(
            "pagu:{$tahun}:{$pengajuan->unit_id}",
            fn (): Pengajuan => DB::transaction(function () use ($pengajuan, $oleh, $catatan): Pengajuan {
                $terkini = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());
                $this->pastikanBelumDiproses($pengajuan, $terkini);

                $tahap = $terkini->tahapBerjalan();

                if (! in_array($tahap, [TahapPersetujuan::KepalaUnit, TahapPersetujuan::Keuangan], true)) {
                    throw new TransisiStatusTidakSahException('Pengajuan tidak sedang menunggu verifikasi.');
                }

                $this->pastikanBolehBertindak($terkini, $oleh, $tahap);

                if ($tahap === TahapPersetujuan::Keuangan && ! $terkini->wajib_wadek) {
                    return $this->setujuiPengajuan->setujui($terkini, $oleh, $tahap, $catatan);
                }

                $ke = $tahap === TahapPersetujuan::KepalaUnit ? StatusPengajuan::DiverifikasiUnit : StatusPengajuan::DiverifikasiKeuangan;
                $dari = $terkini->status;

                if (! $dari->bolehBerpindahKe($ke)) {
                    throw new TransisiStatusTidakSahException("Pengajuan tidak dapat berpindah dari {$dari->getLabel()} ke {$ke->getLabel()}.");
                }

                $terkini->forceFill(['status' => $ke])->save();
                $this->catatRiwayat->handle($terkini, $dari, $ke, $oleh, $catatan, $tahap);

                return $terkini;
            }),
        ));
    }
}
