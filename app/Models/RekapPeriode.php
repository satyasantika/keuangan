<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Snapshot angka per unit saat periode dikunci (LAP-07). Tidak berubah sampai dikunci ulang.
 *
 * @property string $id
 * @property string $periode_id
 * @property string $unit_id
 * @property int $versi_kunci
 * @property string $pagu
 * @property string $total_disetujui_kumulatif
 * @property string $total_pencairan
 * @property string $total_realisasi
 * @property string $total_pajak
 * @property string $total_pengembalian
 * @property string $dibuat_oleh
 * @property-read Unit $unit
 */
class RekapPeriode extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'rekap_periode';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'versi_kunci' => 'integer',
            'pagu' => 'decimal:2',
            'total_disetujui_kumulatif' => 'decimal:2',
            'total_pencairan' => 'decimal:2',
            'total_realisasi' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'total_pengembalian' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<RekapPeriodeAkun, $this> */
    public function perAkun(): HasMany
    {
        return $this->hasMany(RekapPeriodeAkun::class);
    }
}
