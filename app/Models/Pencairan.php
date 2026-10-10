<?php

namespace App\Models;

use App\Concerns\MemilikiRiwayatStatus;
use App\Concerns\MemilikiTautanBerkas;
use App\Concerns\MenjagaPeriodeTerkunci;
use App\Concerns\TercatatAktivitas;
use App\Contracts\PemilikTautan;
use App\Enums\StatusPencairan;
use App\Enums\StatusPeriode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pencairan dana pengajuan (UP/TUP/LS — istilah perlu verifikasi). Tidak pernah dihapus (BR-22).
 *
 * @property string $id
 * @property string $pengajuan_id
 * @property string $jenis_pencairan_id
 * @property string $periode_id
 * @property Carbon $tanggal
 * @property string $jumlah
 * @property string $nomor_bukti
 * @property string|null $penerima
 * @property string|null $keterangan
 * @property StatusPencairan $status
 * @property string $dicatat_oleh
 * @property string|null $dibatalkan_oleh
 * @property Carbon|null $dibatalkan_pada
 * @property string|null $alasan_batal
 * @property-read Pengajuan $pengajuan
 * @property-read JenisPencairan $jenisPencairan
 * @property-read Periode $periode
 */
class Pencairan extends Model implements PemilikTautan
{
    use HasUuids, MemilikiRiwayatStatus, MemilikiTautanBerkas, MenjagaPeriodeTerkunci, TercatatAktivitas;

    protected $table = 'pencairan';

    protected $attributes = ['status' => 'dicatat'];

    /** Status, pembatalan, dan pencatat hanya diisi oleh Action. */
    protected $fillable = ['pengajuan_id', 'jenis_pencairan_id', 'tanggal', 'jumlah', 'nomor_bukti', 'penerima', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah' => 'decimal:2',
            'status' => StatusPencairan::class,
            'dibatalkan_pada' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function kolomTanggalPeriode(): array
    {
        return ['tanggal'];
    }

    /**
     * Tautan bukti hanya berubah selama pencairan aktif, periodenya terbuka, dan belum dibekukan
     * (SPJ diterima membekukan tautan pencairan yang ada saat itu; pencairan tambahan sesudahnya tetap terbuka).
     */
    public function tautanDapatDiubah(): bool
    {
        return $this->status === StatusPencairan::Dicatat
            && Periode::query()->whereKey($this->periode_id)->value('status') === StatusPeriode::Terbuka
            && ! TautanBerkasBeku::query()->where('pemilik_type', $this->getMorphClass())->where('pemilik_id', $this->getKey())->exists();
    }

    /** @return BelongsTo<Pengajuan, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    /** @return BelongsTo<JenisPencairan, $this> */
    public function jenisPencairan(): BelongsTo
    {
        return $this->belongsTo(JenisPencairan::class);
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
