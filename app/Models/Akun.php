<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use Database\Factories\AkunFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Mata anggaran / akun BAS. Data resmi diisi lewat impor; sumber=contoh wajib diganti.
 *
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property string|null $kelompok
 * @property string|null $jenis_belanja
 * @property string $sumber
 * @property bool $aktif
 * @property string|null $keterangan
 */
class Akun extends Model
{
    /** @use HasFactory<AkunFactory> */
    use HasFactory, HasUuids, SoftDeletes, TercatatAktivitas;

    public const KUNCI_CACHE_AKTIF = 'master:akun:aktif';

    public const SUMBER = ['impor' => 'Impor', 'manual' => 'Manual', 'contoh' => 'Contoh'];

    public const JENIS_BELANJA = ['barang' => 'Barang', 'modal' => 'Modal', 'pegawai' => 'Pegawai', 'lainnya' => 'Lainnya'];

    protected $table = 'akun';

    protected $attributes = ['sumber' => 'impor', 'aktif' => true];

    protected $fillable = ['kode', 'nama', 'kelompok', 'jenis_belanja', 'sumber', 'aktif', 'keterangan'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Akun $akun): void {
            if (blank($akun->kelompok) && filled($akun->kode)) {
                $akun->kelompok = substr($akun->kode, 0, 3);
            }
        });

        $lupakan = fn () => self::lupakanCache();

        static::saved($lupakan);
        static::deleted($lupakan);
        static::restored($lupakan);
    }

    public static function lupakanCache(): void
    {
        Cache::forget(self::KUNCI_CACHE_AKTIF);
    }

    /**
     * Akun aktif tercache 24 jam, diurutkan menurut kode.
     *
     * @return Collection<int, Akun>
     */
    public static function aktifTercache(): Collection
    {
        return Cache::remember(self::KUNCI_CACHE_AKTIF, now()->addDay(), fn () => self::query()->where('aktif', true)->orderBy('kode')->get());
    }

    public function contoh(): bool
    {
        return $this->sumber === 'contoh';
    }

    /**
     * Akun yang sudah dipakai rab_item tidak boleh dihapus.
     * Sementara selalu false; F4.1 melengkapinya setelah tabel rab_item ada.
     */
    public function sedangDipakai(): bool
    {
        return false;
    }
}
