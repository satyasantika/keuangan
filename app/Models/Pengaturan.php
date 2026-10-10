<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris pengaturan kebijakan. Baca/tulis lewat App\Support\Pengaturan (tercache, tercatat activitylog).
 *
 * @property string $id
 * @property string $kunci
 * @property string|null $nilai
 * @property string $tipe
 * @property string|null $keterangan
 */
class Pengaturan extends Model
{
    use HasUuids;

    protected $table = 'pengaturan';

    protected $attributes = ['tipe' => 'string'];

    protected $fillable = ['kunci', 'nilai', 'tipe', 'keterangan'];
}
