<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $jenis
 * @property int $tahun
 * @property int $nomor_terakhir
 */
class Penomoran extends Model
{
    use HasUuids;

    protected $table = 'penomoran';

    protected $fillable = ['jenis', 'tahun', 'nomor_terakhir'];

    protected $attributes = ['nomor_terakhir' => 0];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'nomor_terakhir' => 'integer',
        ];
    }
}
