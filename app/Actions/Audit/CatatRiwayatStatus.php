<?php

namespace App\Actions\Audit;

use App\Enums\TahapPersetujuan;
use App\Events\StatusBerubah;
use App\Models\RiwayatStatus;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class CatatRiwayatStatus
{
    /**
     * Wajib dipanggil di dalam DB::transaction agar riwayat dan perubahan status atomik (BR-08).
     */
    public function handle(
        Model $model,
        ?BackedEnum $dari,
        BackedEnum $ke,
        ?User $pengguna,
        ?string $catatan = null,
        ?TahapPersetujuan $tahap = null,
    ): RiwayatStatus {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('CatatRiwayatStatus harus dipanggil di dalam DB::transaction.');
        }

        $riwayat = new RiwayatStatus;
        $riwayat->forceFill([
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'dari' => $dari?->value,
            'ke' => $ke->value,
            'tahap' => $tahap,
            'catatan' => $catatan,
            'user_id' => $pengguna?->getKey(),
        ])->save();

        StatusBerubah::dispatch($model, $dari, $ke, $pengguna, $tahap, $catatan);

        return $riwayat;
    }
}
