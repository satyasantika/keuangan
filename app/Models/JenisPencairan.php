<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Jenis pencairan (UP/TUP/LS — istilah perlu verifikasi). Pola hanya konfigurasi (BR-25).
 *
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property string $pola
 * @property string|null $keterangan
 * @property bool $perlu_verifikasi
 * @property bool $aktif
 */
class JenisPencairan extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    public const KUNCI_CACHE_AKTIF = 'master:jenis-pencairan:aktif';

    public const POLA = ['semua' => 'Semua', 'satker' => 'Satker', 'blu' => 'BLU'];

    protected $table = 'jenis_pencairan';

    protected $attributes = ['pola' => 'semua', 'perlu_verifikasi' => true, 'aktif' => true];

    protected $fillable = ['kode', 'nama', 'pola', 'keterangan', 'perlu_verifikasi', 'aktif'];

    protected function casts(): array
    {
        return [
            'perlu_verifikasi' => 'boolean',
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
}
