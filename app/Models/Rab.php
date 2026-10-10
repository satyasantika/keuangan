<?php

namespace App\Models;

use App\Concerns\MemilikiRiwayatStatus;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusRab;
use Database\Factories\RabFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Versi RAB: 0 = awal, 1, 2, … = revisi (BR-10).
 *
 * @property string $id
 * @property string $pengajuan_id
 * @property int $nomor_revisi
 * @property StatusRab $status
 * @property string $total
 * @property string|null $alasan_revisi
 * @property string $dibuat_oleh
 * @property Carbon|null $diajukan_pada
 * @property string|null $disetujui_oleh
 * @property Carbon|null $disetujui_pada
 * @property-read Pengajuan $pengajuan
 */
class Rab extends Model
{
    /** @use HasFactory<RabFactory> */
    use HasFactory, HasUuids, MemilikiRiwayatStatus, TercatatAktivitas;

    protected $table = 'rab';

    protected $attributes = ['status' => 'draf', 'nomor_revisi' => 0, 'total' => '0.00'];

    /** Status dan total hanya diubah oleh Action. */
    protected $fillable = ['pengajuan_id', 'nomor_revisi', 'alasan_revisi', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'nomor_revisi' => 'integer',
            'status' => StatusRab::class,
            'total' => 'decimal:2',
            'diajukan_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pengajuan, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    /**
     * Pengajuan pemilik (memakai relasi termuat bila ada, tanpa memicu lazy loading).
     */
    public function pengajuanPemilik(): Pengajuan
    {
        return $this->relationLoaded('pengajuan') ? $this->pengajuan : $this->pengajuan()->firstOrFail();
    }

    /** @return HasMany<RabItem, $this> */
    public function item(): HasMany
    {
        return $this->hasMany(RabItem::class)->orderBy('urutan');
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
