<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Enums\StatusPagu;
use App\Exceptions\KesalahanDomain;
use App\Support\Uang;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian pagu per akun (opsional). Σ rincian ≤ pagu.jumlah.
 *
 * @property string $id
 * @property string $pagu_id
 * @property string $akun_id
 * @property string $jumlah
 * @property-read Pagu $pagu
 * @property-read Akun $akun
 */
class PaguAkun extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'pagu_akun';

    protected $fillable = ['pagu_id', 'akun_id', 'jumlah'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PaguAkun $rincian): void {
            if (! preg_match('/^\d+(\.\d{1,2})?$/', (string) $rincian->jumlah)) {
                throw new KesalahanDomain('Jumlah rincian akun harus angka desimal tidak negatif.');
            }

            $pagu = Pagu::query()->findOrFail($rincian->pagu_id);

            if ($pagu->status !== StatusPagu::Draf) {
                throw new KesalahanDomain('Rincian akun hanya dapat diubah selama pagu berstatus draf.');
            }

            $total = Uang::jumlahkan([$pagu->totalRincianAkun($rincian->exists ? $rincian->id : null), (string) $rincian->jumlah]);

            if (Uang::bandingkan($total, (string) $pagu->jumlah) > 0) {
                throw new KesalahanDomain('Σ rincian akun ('.Uang::format($total).') melebihi pagu ('.Uang::format((string) $pagu->jumlah).').');
            }
        });

        static::deleting(function (PaguAkun $rincian): void {
            if ($rincian->pagu()->toBase()->value('status') !== StatusPagu::Draf->value) {
                throw new KesalahanDomain('Rincian akun hanya dapat dihapus selama pagu berstatus draf.');
            }
        });
    }

    /** @return BelongsTo<Pagu, $this> */
    public function pagu(): BelongsTo
    {
        return $this->belongsTo(Pagu::class);
    }

    /** @return BelongsTo<Akun, $this> */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class);
    }
}
