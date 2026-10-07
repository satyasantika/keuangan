<?php

namespace App\Events;

use App\Enums\TahapPersetujuan;
use App\Models\User;
use BackedEnum;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Terkirim setelah transaksi commit; listener notifikasi dipasang di F10.
 */
class StatusBerubah implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Model $model,
        public ?BackedEnum $dari,
        public BackedEnum $ke,
        public ?User $pengguna,
        public ?TahapPersetujuan $tahap = null,
        public ?string $catatan = null,
    ) {}
}
