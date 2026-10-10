<?php

namespace App\Actions\Pengajuan;

use App\Models\Rab;
use App\Models\RabItem;
use App\Support\Uang;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat menghitung jumlah item dan total RAB (BR-01, BR-02).
 */
class HitungTotalRab
{
    public function handle(Rab $rab): Rab
    {
        return DB::transaction(function () use ($rab): Rab {
            $jumlah = [];

            /** @var RabItem $item */
            foreach ($rab->item()->get() as $item) {
                $item->jumlah = Uang::kali((string) $item->volume, (string) $item->harga_satuan);
                $item->saveQuietly();
                $jumlah[] = $item->jumlah;
            }

            $rab->forceFill(['total' => Uang::jumlahkan($jumlah)])->save();

            if ($rab->nomor_revisi === 0) {
                $rab->pengajuan()->firstOrFail()->forceFill(['total_diajukan' => $rab->total])->save();
            }

            return $rab;
        });
    }
}
