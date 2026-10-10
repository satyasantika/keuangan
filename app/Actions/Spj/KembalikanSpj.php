<?php

namespace App\Actions\Spj;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusSpj;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Spj;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * diajukan → dikembalikan dengan catatan wajib (BR-17).
 */
class KembalikanSpj
{
    public const MIN_CATATAN = 10;

    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Spj $spj, User $oleh, string $catatan): Spj
    {
        $catatan = trim($catatan);

        if (mb_strlen($catatan) < self::MIN_CATATAN) {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi minimal '.self::MIN_CATATAN.' karakter.']);
        }

        TerimaSpj::pastikanVerifikator($spj, $oleh);

        return DenganKunci::jalankan("aksi:spj:{$spj->id}", fn (): Spj => DB::transaction(function () use ($spj, $oleh, $catatan): Spj {
            $terkini = Spj::query()->lockForUpdate()->findOrFail($spj->getKey());

            if ($terkini->status !== StatusSpj::Diajukan) {
                throw new TransisiStatusTidakSahException('SPJ sudah diproses pengguna lain atau belum diajukan.');
            }

            $terkini->forceFill([
                'status' => StatusSpj::Dikembalikan,
                'diputuskan_oleh' => $oleh->getKey(),
                'diputuskan_pada' => now(),
                'catatan_verifikasi' => $catatan,
            ])->save();

            $this->catatRiwayat->handle($terkini, StatusSpj::Diajukan, StatusSpj::Dikembalikan, $oleh, $catatan, TerimaSpj::tahap($oleh));

            return $terkini;
        }));
    }
}
