<?php

namespace App\Models;

use App\Enums\TahapPersetujuan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Riwayat transisi status (BR-08). Baris tidak pernah diubah.
 *
 * @property string $id
 * @property string $model_type
 * @property string $model_id
 * @property string|null $dari
 * @property string $ke
 * @property TahapPersetujuan|null $tahap
 * @property string|null $catatan
 * @property string|null $user_id
 * @property Carbon $created_at
 */
class RiwayatStatus extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'riwayat_status';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tahap' => TahapPersetujuan::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
