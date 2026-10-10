<?php

namespace App\Models;

use App\Exceptions\KesalahanDomain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot tautan bukti saat SPJ diterima/pengembalian diverifikasi (BR-17). Tidak pernah diubah/dihapus.
 *
 * @property string $id
 * @property string $spj_id
 * @property string $riwayat_status_id
 * @property string $tautan_berkas_id
 * @property string $pemilik_type
 * @property string $pemilik_id
 * @property string $jenis
 * @property string $label
 * @property string $url
 * @property string|null $drive_file_id
 * @property string $status_cek
 * @property Carbon|null $dicek_pada
 * @property Carbon $dibekukan_pada
 * @property-read TautanBerkas $tautanBerkas
 */
class TautanBerkasBeku extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'tautan_berkas_beku';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dicek_pada' => 'datetime',
            'dibekukan_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new KesalahanDomain('Snapshot tautan beku tidak dapat diubah.'));
        static::deleting(fn () => throw new KesalahanDomain('Snapshot tautan beku tidak dapat dihapus.'));
    }

    /** @return BelongsTo<Spj, $this> */
    public function spj(): BelongsTo
    {
        return $this->belongsTo(Spj::class);
    }

    /** @return BelongsTo<TautanBerkas, $this> */
    public function tautanBerkas(): BelongsTo
    {
        return $this->belongsTo(TautanBerkas::class)->withTrashed();
    }

    /** @return BelongsTo<RiwayatStatus, $this> */
    public function riwayatStatus(): BelongsTo
    {
        return $this->belongsTo(RiwayatStatus::class);
    }
}
