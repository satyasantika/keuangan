<?php

namespace App\Actions\Master;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UbahStatusTahunAnggaran
{
    public function __construct(private readonly CatatRiwayatStatus $catatRiwayat) {}

    public function handle(TahunAnggaran $tahunAnggaran, StatusTahunAnggaran $ke, User $oleh, ?string $catatan = null): TahunAnggaran
    {
        if (! $oleh->can('update', $tahunAnggaran)) {
            throw new AuthorizationException('Anda tidak berhak mengubah status tahun anggaran.');
        }

        return DenganKunci::jalankan("aksi:tahun-anggaran:{$tahunAnggaran->id}", function () use ($tahunAnggaran, $ke, $oleh, $catatan): TahunAnggaran {
            return DB::transaction(function () use ($tahunAnggaran, $ke, $oleh, $catatan): TahunAnggaran {
                $terkini = TahunAnggaran::query()->lockForUpdate()->findOrFail($tahunAnggaran->getKey());
                $dari = $terkini->status;

                if (! $dari->bolehBerpindahKe($ke)) {
                    throw new TransisiStatusTidakSahException("Status tahun anggaran tidak dapat diubah dari {$dari->getLabel()} ke {$ke->getLabel()}.");
                }

                $terkini->forceFill(['status' => $ke])->save();
                $this->catatRiwayat->handle($terkini, $dari, $ke, $oleh, $catatan);

                return $terkini;
            });
        });
    }
}
