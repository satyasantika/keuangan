<?php

namespace App\Actions\Persetujuan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dasar kembalikan/tolak oleh pemegang tahap yang sedang berjalan (BR-06: catatan wajib min. 10 karakter).
 */
abstract class KeputusanNegatif
{
    use MemeriksaPemisahanTugas;

    public const MIN_CATATAN = 10;

    public function __construct(protected readonly CatatRiwayatStatus $catatRiwayat) {}

    abstract protected function statusTujuan(): StatusPengajuan;

    abstract protected function statusRabTujuan(): StatusRab;

    public function handle(Pengajuan $pengajuan, User $oleh, string $catatan): Pengajuan
    {
        $catatan = trim($catatan);

        if (mb_strlen($catatan) < self::MIN_CATATAN) {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi minimal '.self::MIN_CATATAN.' karakter.']);
        }

        return DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", function () use ($pengajuan, $oleh, $catatan): Pengajuan {
            return DB::transaction(function () use ($pengajuan, $oleh, $catatan): Pengajuan {
                $terkini = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());
                $this->pastikanBelumDiproses($pengajuan, $terkini);

                $tahap = $this->pastikanBolehBertindak($terkini, $oleh);
                $dari = $terkini->status;
                $ke = $this->statusTujuan();

                if (! $dari->bolehBerpindahKe($ke)) {
                    throw new TransisiStatusTidakSahException("Pengajuan tidak dapat berpindah dari {$dari->getLabel()} ke {$ke->getLabel()}.");
                }

                $terkini->forceFill(['status' => $ke])->save();
                $this->catatRiwayat->handle($terkini, $dari, $ke, $oleh, $catatan, $tahap);

                $rab = $terkini->rabAwal()->lockForUpdate()->first();

                if ($rab !== null && $rab->status->bolehBerpindahKe($this->statusRabTujuan())) {
                    $statusRab = $rab->status;
                    $rab->forceFill(['status' => $this->statusRabTujuan()])->save();
                    $this->catatRiwayat->handle($rab, $statusRab, $this->statusRabTujuan(), $oleh, $catatan, $tahap);
                }

                return $terkini;
            });
        });
    }
}
