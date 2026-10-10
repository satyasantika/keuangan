<?php

namespace App\Models;

use App\Concerns\MemilikiRiwayatStatus;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusSpj;
use Database\Factories\SpjFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Surat pertanggungjawaban: satu per pengajuan (BR-17).
 *
 * @property string $id
 * @property string $pengajuan_id
 * @property string|null $nomor
 * @property StatusSpj $status
 * @property string $total_realisasi
 * @property string $total_pajak
 * @property string $dibuat_oleh
 * @property Carbon|null $diajukan_pada
 * @property string|null $pernyataan_final_oleh
 * @property Carbon|null $pernyataan_final_pada
 * @property string|null $diputuskan_oleh
 * @property Carbon|null $diputuskan_pada
 * @property string|null $catatan_verifikasi
 * @property-read Pengajuan $pengajuan
 */
class Spj extends Model
{
    /** @use HasFactory<SpjFactory> */
    use HasFactory, HasUuids, MemilikiRiwayatStatus, TercatatAktivitas;

    protected $table = 'spj';

    protected $attributes = ['status' => 'draf', 'total_realisasi' => '0.00', 'total_pajak' => '0.00'];

    /** Status, nomor, total, pernyataan, dan keputusan hanya diisi oleh Action. */
    protected $fillable = ['pengajuan_id', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'status' => StatusSpj::class,
            'total_realisasi' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'diajukan_pada' => 'datetime',
            'pernyataan_final_pada' => 'datetime',
            'diputuskan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pengajuan, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    /** @return HasMany<RealisasiItem, $this> */
    public function realisasiItem(): HasMany
    {
        return $this->hasMany(RealisasiItem::class)->orderBy('tanggal_bukti');
    }

    /** @return HasMany<TautanBerkasBeku, $this> */
    public function tautanBeku(): HasMany
    {
        return $this->hasMany(TautanBerkasBeku::class)->orderBy('dibekukan_pada');
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
