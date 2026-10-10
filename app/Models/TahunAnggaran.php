<?php

namespace App\Models;

use App\Concerns\MemilikiRiwayatStatus;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusTahunAnggaran;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $tahun
 * @property string $nama
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property StatusTahunAnggaran $status
 * @property string|null $keterangan
 */
class TahunAnggaran extends Model
{
    use HasUuids, MemilikiRiwayatStatus, SoftDeletes, TercatatAktivitas;

    protected $table = 'tahun_anggaran';

    protected $attributes = ['status' => 'perencanaan'];

    /** Kolom status hanya diubah oleh Action (CLAUDE.md). */
    protected $fillable = ['tahun', 'nama', 'tanggal_mulai', 'tanggal_selesai', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status' => StatusTahunAnggaran::class,
        ];
    }

    /**
     * Tahun anggaran bawaan untuk dasbor/laporan: yang berjalan terbaru, atau yang terbaru.
     */
    public static function bawaanId(): ?string
    {
        return self::query()->where('status', StatusTahunAnggaran::Berjalan->value)->orderByDesc('tahun')->value('id')
            ?? self::query()->orderByDesc('tahun')->value('id');
    }

    /** @return HasMany<Periode, $this> */
    public function periode(): HasMany
    {
        return $this->hasMany(Periode::class)->orderBy('bulan');
    }
}
