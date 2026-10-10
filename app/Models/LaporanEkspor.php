<?php

namespace App\Models;

use App\Enums\StatusEkspor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ekspor laporan berantrean; berkas hanya SEMENTARA di storage/app/tmp/ekspor (≤ 24 jam).
 *
 * @property string $id
 * @property string $jenis
 * @property array<string, mixed> $parameter
 * @property string $format
 * @property StatusEkspor $status
 * @property string|null $path
 * @property int|null $ukuran_byte
 * @property string $diminta_oleh
 * @property Carbon|null $mulai_pada
 * @property Carbon|null $selesai_pada
 * @property Carbon|null $kedaluwarsa_pada
 * @property string|null $pesan_galat
 * @property-read User $peminta
 */
class LaporanEkspor extends Model
{
    use HasUuids;

    protected $table = 'laporan_ekspor';

    protected $attributes = ['status' => 'antre'];

    protected $fillable = ['jenis', 'parameter', 'format', 'diminta_oleh'];

    protected function casts(): array
    {
        return [
            'parameter' => 'array',
            'status' => StatusEkspor::class,
            'ukuran_byte' => 'integer',
            'mulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'kedaluwarsa_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function peminta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diminta_oleh');
    }

    public function dapatDiunduh(): bool
    {
        return $this->status === StatusEkspor::Selesai
            && $this->path !== null
            && ($this->kedaluwarsa_pada === null || $this->kedaluwarsa_pada->isFuture())
            && is_file(storage_path('app/'.$this->path));
    }
}
