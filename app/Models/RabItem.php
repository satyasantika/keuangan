<?php

namespace App\Models;

use App\Enums\StatusRab;
use App\Exceptions\KesalahanDomain;
use Database\Factories\RabItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item RAB; jumlah = volume × harga_satuan dihitung server (BR-02).
 *
 * @property string $id
 * @property string $rab_id
 * @property string $akun_id
 * @property string|null $asal_rab_item_id
 * @property int $urutan
 * @property string $uraian
 * @property string|null $rincian_volume
 * @property string $volume
 * @property string $satuan
 * @property string $harga_satuan
 * @property string $jumlah
 * @property string|null $keterangan
 * @property-read Rab $rab
 * @property-read Akun $akun
 * @property-read RabItem|null $asal
 */
class RabItem extends Model
{
    /** @use HasFactory<RabItemFactory> */
    use HasFactory, HasUuids;

    protected $table = 'rab_item';

    protected $attributes = ['urutan' => 0, 'jumlah' => '0.00'];

    /** Kolom jumlah dihitung HitungTotalRab, tidak diisi dari klien. */
    protected $fillable = ['rab_id', 'akun_id', 'asal_rab_item_id', 'urutan', 'uraian', 'rincian_volume', 'volume', 'satuan', 'harga_satuan', 'keterangan'];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'volume' => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'jumlah' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // BR-03: item hanya berubah selama RAB draf (guard model selain Policy).
        $jaga = function (RabItem $item): void {
            if (Rab::query()->whereKey($item->rab_id)->toBase()->value('status') !== StatusRab::Draf->value) {
                throw new KesalahanDomain('RAB sudah terkunci.');
            }
        };

        static::saving($jaga);
        static::deleting($jaga);
    }

    /** @return BelongsTo<Rab, $this> */
    public function rab(): BelongsTo
    {
        return $this->belongsTo(Rab::class);
    }

    /** @return BelongsTo<Akun, $this> */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class);
    }

    /** @return BelongsTo<RabItem, $this> */
    public function asal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'asal_rab_item_id');
    }
}
