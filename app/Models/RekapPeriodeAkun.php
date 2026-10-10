<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $rekap_periode_id
 * @property string $akun_id
 * @property string $total_disetujui_kumulatif
 * @property string $total_realisasi
 */
class RekapPeriodeAkun extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'rekap_periode_akun';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_disetujui_kumulatif' => 'decimal:2',
            'total_realisasi' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Akun, $this> */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class);
    }
}
