<?php

namespace App\Actions\Revisi;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusRab;
use App\Enums\TahapPersetujuan;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Rab;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dasar kembalikan/tolak revisi oleh pemegang tahap berjalan; catatan wajib.
 */
abstract class KeputusanNegatifRevisi
{
    public const MIN_CATATAN = 10;

    public function __construct(protected readonly CatatRiwayatStatus $catatRiwayat) {}

    abstract protected function tujuan(): StatusRab;

    public function handle(Rab $rab, User $oleh, string $catatan): Rab
    {
        $catatan = trim($catatan);

        if (mb_strlen($catatan) < self::MIN_CATATAN) {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi minimal '.self::MIN_CATATAN.' karakter.']);
        }

        if (! $oleh->can('putuskan', $rab)) {
            throw new AuthorizationException('Anda tidak berhak memutuskan revisi ini.');
        }

        return DenganKunci::jalankan("aksi:rab:{$rab->id}", fn (): Rab => DB::transaction(function () use ($rab, $oleh, $catatan): Rab {
            $terkini = Rab::query()->lockForUpdate()->findOrFail($rab->getKey());
            $dari = $terkini->status;

            if (! in_array($dari, [StatusRab::Diajukan, StatusRab::DiverifikasiKeuangan], true) || ! $dari->bolehBerpindahKe($this->tujuan())) {
                throw new TransisiStatusTidakSahException('Revisi sudah diproses pengguna lain.');
            }

            $tahap = $dari === StatusRab::Diajukan ? TahapPersetujuan::Keuangan : TahapPersetujuan::Wadek;

            $terkini->forceFill(['status' => $this->tujuan()])->save();
            $this->catatRiwayat->handle($terkini, $dari, $this->tujuan(), $oleh, $catatan, $tahap);

            return $terkini;
        }));
    }
}
