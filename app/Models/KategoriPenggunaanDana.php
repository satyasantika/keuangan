<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kategori penggunaan dana untuk laporan akreditasi (LAP-08) — format perlu verifikasi.
 *
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property int $urutan
 * @property bool $aktif
 */
class KategoriPenggunaanDana extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    protected $table = 'kategori_penggunaan_dana';

    protected $attributes = ['urutan' => 0, 'aktif' => true];

    protected $fillable = ['kode', 'nama', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }
}
