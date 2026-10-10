<?php

namespace App\Actions\Pengembalian;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusPengembalian;
use App\Enums\TahapPersetujuan;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengembalian;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TolakPengembalian
{
    public const MIN_CATATAN = 10;

    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(Pengembalian $pengembalian, User $oleh, string $catatan): Pengembalian
    {
        $catatan = trim($catatan);

        if (mb_strlen($catatan) < self::MIN_CATATAN) {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi minimal '.self::MIN_CATATAN.' karakter.']);
        }

        VerifikasiPengembalian::pastikanVerifikator($pengembalian, $oleh);

        return DenganKunci::jalankan("aksi:pengembalian:{$pengembalian->id}", fn (): Pengembalian => DB::transaction(function () use ($pengembalian, $oleh, $catatan): Pengembalian {
            $terkini = Pengembalian::query()->lockForUpdate()->findOrFail($pengembalian->getKey());

            if (! $terkini->status->bolehBerpindahKe(StatusPengembalian::Ditolak)) {
                throw new TransisiStatusTidakSahException('Pengembalian sudah diproses.');
            }

            $terkini->forceFill([
                'status' => StatusPengembalian::Ditolak,
                'diputuskan_oleh' => $oleh->getKey(),
                'diputuskan_pada' => now(),
                'catatan' => $catatan,
            ])->save();

            $this->catatRiwayat->handle($terkini, StatusPengembalian::Diajukan, StatusPengembalian::Ditolak, $oleh, $catatan, TahapPersetujuan::Bendahara);

            return $terkini;
        }));
    }
}
