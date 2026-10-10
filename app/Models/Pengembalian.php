<?php

namespace App\Models;

use App\Concerns\MemilikiRiwayatStatus;
use App\Concerns\MemilikiTautanBerkas;
use App\Concerns\MenjagaPeriodeTerkunci;
use App\Concerns\TercatatAktivitas;
use App\Contracts\PemilikTautan;
use App\Enums\StatusPengembalian;
use Database\Factories\PengembalianFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Setoran pengembalian sisa dana (BR-18).
 *
 * @property string $id
 * @property string $pengajuan_id
 * @property string $periode_id
 * @property Carbon $tanggal_setor
 * @property string $jumlah
 * @property string $nomor_bukti_setor
 * @property string|null $keterangan
 * @property StatusPengembalian $status
 * @property string $dibuat_oleh
 * @property string|null $diputuskan_oleh
 * @property Carbon|null $diputuskan_pada
 * @property string|null $catatan
 * @property-read Pengajuan $pengajuan
 */
class Pengembalian extends Model implements PemilikTautan
{
    /** @use HasFactory<PengembalianFactory> */
    use HasFactory, HasUuids, MemilikiRiwayatStatus, MemilikiTautanBerkas, MenjagaPeriodeTerkunci, TercatatAktivitas;

    protected $table = 'pengembalian';

    protected $attributes = ['status' => 'diajukan'];

    protected $fillable = ['pengajuan_id', 'tanggal_setor', 'jumlah', 'nomor_bukti_setor', 'keterangan', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'tanggal_setor' => 'date',
            'jumlah' => 'decimal:2',
            'status' => StatusPengembalian::class,
            'diputuskan_pada' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function kolomTanggalPeriode(): array
    {
        return ['tanggal_setor'];
    }

    public function tautanDapatDiubah(): bool
    {
        return $this->status === StatusPengembalian::Diajukan;
    }

    /** @return BelongsTo<Pengajuan, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
