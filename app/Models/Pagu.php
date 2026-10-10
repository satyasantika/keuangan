<?php

namespace App\Models;

use App\Concerns\DibatasiUnit;
use App\Concerns\MemilikiRiwayatStatus;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusPagu;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Support\Uang;
use Database\Factories\PaguFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Pagu unit per tahun anggaran; berlaku setelah ditetapkan wadek-keuangan (BR-04).
 *
 * @property string $id
 * @property string $tahun_anggaran_id
 * @property string $unit_id
 * @property string $jumlah
 * @property string|null $sumber_dana
 * @property StatusPagu $status
 * @property string|null $keterangan
 * @property string $dibuat_oleh
 * @property string|null $ditetapkan_oleh
 * @property Carbon|null $ditetapkan_pada
 * @property-read TahunAnggaran $tahunAnggaran
 * @property-read Unit $unit
 */
class Pagu extends Model
{
    /** @use HasFactory<PaguFactory> */
    use DibatasiUnit, HasFactory, HasUuids, MemilikiRiwayatStatus, SoftDeletes, TercatatAktivitas;

    protected $table = 'pagu';

    protected $attributes = ['status' => 'draf', 'jumlah' => '0.00'];

    /** Status dan penetapan hanya diubah oleh Action. */
    protected $fillable = ['tahun_anggaran_id', 'unit_id', 'jumlah', 'sumber_dana', 'keterangan', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'status' => StatusPagu::class,
            'ditetapkan_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Pagu $pagu): void {
            if (! preg_match('/^\d+(\.\d{1,2})?$/', (string) $pagu->jumlah)) {
                throw new KesalahanDomain('Jumlah pagu harus angka desimal tidak negatif.');
            }

            $tahun = TahunAnggaran::query()->find($pagu->tahun_anggaran_id);

            if ($tahun?->status === StatusTahunAnggaran::Ditutup) {
                throw new KesalahanDomain("Tahun anggaran {$tahun->tahun} sudah ditutup.");
            }

            if ($pagu->exists && $pagu->isDirty('jumlah')) {
                $rincian = $pagu->totalRincianAkun();

                if (Uang::bandingkan($rincian, (string) $pagu->jumlah) > 0) {
                    throw new KesalahanDomain('Jumlah pagu tidak boleh lebih kecil dari Σ rincian akun ('.Uang::format($rincian).').');
                }
            }
        });
    }

    /** @return BelongsTo<TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    /** @return HasMany<PaguAkun, $this> */
    public function rincianAkun(): HasMany
    {
        return $this->hasMany(PaguAkun::class);
    }

    /**
     * Hanya pagu ditetapkan yang berlaku (draf tidak dihitung).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('status', StatusPagu::Ditetapkan->value);
    }

    public static function berlakuUntuk(string $tahunAnggaranId, string $unitId): ?self
    {
        return self::query()->berlaku()->where('tahun_anggaran_id', $tahunAnggaranId)->where('unit_id', $unitId)->first();
    }

    public function berlaku(): bool
    {
        return $this->status === StatusPagu::Ditetapkan;
    }

    /**
     * Σ rincian akun dihitung server (BR-01).
     */
    public function totalRincianAkun(?string $kecualiId = null): string
    {
        return Uang::jumlahkan(
            $this->rincianAkun()
                ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
                ->pluck('jumlah')
                ->map(fn ($j): string => (string) $j)
                ->all(),
        );
    }
}
