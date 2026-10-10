<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property bool $aktif
 */
class JenisPajak extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    protected $table = 'jenis_pajak';

    protected $attributes = ['aktif' => true];

    protected $fillable = ['kode', 'nama', 'aktif'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    /** @return HasMany<TarifPajak, $this> */
    public function tarif(): HasMany
    {
        return $this->hasMany(TarifPajak::class);
    }
}
