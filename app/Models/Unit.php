<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Enums\JenisUnit;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Unit/prodi pemilik anggaran.
 *
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property JenisUnit $jenis
 * @property string|null $jenjang
 * @property string|null $kepala_user_id
 * @property string|null $kode_eksternal
 * @property bool $aktif
 * @property-read User|null $kepala
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasUuids, SoftDeletes, TercatatAktivitas;

    public const KUNCI_CACHE_AKTIF = 'master:unit:aktif';

    protected $table = 'unit';

    protected $attributes = ['jenis' => 'prodi', 'aktif' => true];

    protected $fillable = ['kode', 'nama', 'jenis', 'jenjang', 'kepala_user_id', 'kode_eksternal', 'aktif'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisUnit::class,
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $lupakan = fn () => Cache::forget(self::KUNCI_CACHE_AKTIF);

        static::saved($lupakan);
        static::deleted($lupakan);
        static::restored($lupakan);
    }

    /** @return BelongsTo<User, $this> */
    public function kepala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepala_user_id');
    }

    /** @return HasMany<User, $this> */
    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Unit aktif tercache 24 jam, diurutkan menurut kode.
     *
     * @return Collection<int, Unit>
     */
    public static function aktifTercache(): Collection
    {
        return Cache::remember(self::KUNCI_CACHE_AKTIF, now()->addDay(), fn () => self::query()->where('aktif', true)->orderBy('kode')->get());
    }
}
