<?php

namespace App\Actions\Spj;

use App\Enums\StatusPengajuan;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan SPJ draf untuk pengajuan yang sudah dicairkan (satu SPJ per pengajuan).
 */
class SiapkanSpj
{
    public function handle(Pengajuan $pengajuan, User $oleh): Spj
    {
        $ada = $pengajuan->spj()->first();

        if ($ada !== null) {
            return $ada;
        }

        if (! $oleh->can('create', RealisasiItem::class) || $oleh->unit_id !== $pengajuan->unit_id) {
            throw new AuthorizationException('Anda tidak berhak menyusun SPJ pengajuan ini.');
        }

        if ($pengajuan->status !== StatusPengajuan::Dicairkan) {
            throw new TransisiStatusTidakSahException('SPJ disusun setelah pengajuan dicairkan.');
        }

        return DenganKunci::jalankan("aksi:pengajuan-dana:{$pengajuan->id}", fn (): Spj => DB::transaction(
            fn (): Spj => Spj::query()->firstOrCreate(['pengajuan_id' => $pengajuan->id], ['dibuat_oleh' => $oleh->getKey()]),
        ));
    }
}
