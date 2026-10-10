<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Contracts\PemilikTautan;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\StatusCekTautan;
use App\Exceptions\KesalahanDomain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tautan lampiran/bukti (BR-15). Perubahan URL tercatat activitylog.
 *
 * @property string $id
 * @property string $pemilik_type
 * @property string $pemilik_id
 * @property JenisTautan $jenis
 * @property string $label
 * @property string $url
 * @property PenyediaTautan $penyedia
 * @property string|null $drive_file_id
 * @property StatusCekTautan $status_cek
 * @property Carbon|null $dicek_pada
 * @property bool $akses_terbatas_dikonfirmasi
 * @property bool $peringatan_privasi
 * @property string $ditambahkan_oleh
 * @property-read Model|null $pemilik
 * @property-read User $penambah
 */
class TautanBerkas extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    /** Kolom hasil pemeriksaan yang tetap boleh diperbarui walau pemilik terkunci. */
    public const KOLOM_PEMERIKSAAN = ['status_cek', 'dicek_pada', 'peringatan_privasi', 'updated_at'];

    protected $table = 'tautan_berkas';

    protected $attributes = ['status_cek' => 'belum', 'akses_terbatas_dikonfirmasi' => false, 'peringatan_privasi' => false];

    protected $fillable = ['pemilik_type', 'pemilik_id', 'jenis', 'label', 'url', 'penyedia', 'drive_file_id', 'akses_terbatas_dikonfirmasi', 'ditambahkan_oleh'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisTautan::class,
            'penyedia' => PenyediaTautan::class,
            'status_cek' => StatusCekTautan::class,
            'dicek_pada' => 'datetime',
            'akses_terbatas_dikonfirmasi' => 'boolean',
            'peringatan_privasi' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TautanBerkas $tautan): void {
            $hanyaPemeriksaan = $tautan->exists && array_diff(array_keys($tautan->getDirty()), self::KOLOM_PEMERIKSAAN) === [];

            if (! $hanyaPemeriksaan) {
                $tautan->pastikanDapatDiubah();
            }
        });

        static::deleting(fn (TautanBerkas $tautan) => $tautan->pastikanDapatDiubah());
    }

    /** @return MorphTo<Model, $this> */
    public function pemilik(): MorphTo
    {
        return $this->morphTo('pemilik');
    }

    /** @return BelongsTo<User, $this> */
    public function penambah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditambahkan_oleh');
    }

    private function pastikanDapatDiubah(): void
    {
        $pemilik = $this->pemilik()->first();

        if ($pemilik instanceof PemilikTautan && ! $pemilik->tautanDapatDiubah()) {
            throw new KesalahanDomain('Tautan sudah terkunci.');
        }
    }
}
