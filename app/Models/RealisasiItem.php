<?php

namespace App\Models;

use App\Concerns\MemilikiTautanBerkas;
use App\Concerns\MenjagaPeriodeTerkunci;
use App\Concerns\TercatatAktivitas;
use App\Contracts\PemilikTautan;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use Database\Factories\RealisasiItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Realisasi per item RAB dengan tautan bukti (BR-14, BR-15).
 *
 * @property string $id
 * @property string $spj_id
 * @property string $rab_item_id
 * @property string $periode_id
 * @property Carbon $tanggal_bukti
 * @property string $nomor_bukti
 * @property string $uraian
 * @property string|null $penerima
 * @property string $volume
 * @property string $harga_satuan
 * @property string $jumlah_bruto
 * @property string $total_pajak
 * @property string $jumlah_neto
 * @property bool $indikasi_bukti_ganda
 * @property string $dibuat_oleh
 * @property-read Spj $spj
 * @property-read RabItem $rabItem
 */
class RealisasiItem extends Model implements PemilikTautan
{
    /** @use HasFactory<RealisasiItemFactory> */
    use HasFactory, HasUuids, MemilikiTautanBerkas, MenjagaPeriodeTerkunci, SoftDeletes, TercatatAktivitas;

    /**
     * Pengecualian guard HANYA untuk pemetaan realisasi saat revisi RAB berlaku (BR-11).
     * Selalu diaktifkan lewat petakanTanpaPenjaga() yang me-reset di finally.
     */
    private static bool $pemetaanRevisi = false;

    protected $table = 'realisasi_item';

    protected $attributes = ['total_pajak' => '0.00', 'indikasi_bukti_ganda' => false];

    /** Jumlah bruto/neto & total pajak dihitung Action. */
    protected $fillable = ['spj_id', 'rab_item_id', 'tanggal_bukti', 'nomor_bukti', 'uraian', 'penerima', 'volume', 'harga_satuan', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'tanggal_bukti' => 'date',
            'volume' => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'jumlah_bruto' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'jumlah_neto' => 'decimal:2',
            'indikasi_bukti_ganda' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $jaga = function (RealisasiItem $item): void {
            if (self::$pemetaanRevisi) {
                return;
            }

            if (! $item->spjDapatDiubah()) {
                throw new KesalahanDomain('Realisasi terkunci: SPJ sudah diajukan atau diterima.');
            }
        };

        static::saving($jaga);
        static::deleting($jaga);
    }

    /**
     * Memindahkan realisasi dari item RAB lama ke item revisi (tanpa event model) dengan flag yang
     * selalu dikembalikan ke false.
     */
    public static function petakanTanpaPenjaga(string $itemLamaId, string $itemBaruId): int
    {
        self::$pemetaanRevisi = true;

        try {
            return self::withTrashed()->where('rab_item_id', $itemLamaId)->update(['rab_item_id' => $itemBaruId]);
        } finally {
            self::$pemetaanRevisi = false;
        }
    }

    public static function sedangMemetakanRevisi(): bool
    {
        return self::$pemetaanRevisi;
    }

    /**
     * @return array<int, string>
     */
    protected function kolomTanggalPeriode(): array
    {
        return ['tanggal_bukti'];
    }

    public function spjDapatDiubah(): bool
    {
        $status = Spj::query()->whereKey($this->spj_id)->toBase()->value('status');

        return $status !== null && StatusSpj::from((string) $status)->dapatDiubah();
    }

    public function tautanDapatDiubah(): bool
    {
        return $this->spjDapatDiubah();
    }

    /** @return BelongsTo<Spj, $this> */
    public function spj(): BelongsTo
    {
        return $this->belongsTo(Spj::class);
    }

    /** @return BelongsTo<RabItem, $this> */
    public function rabItem(): BelongsTo
    {
        return $this->belongsTo(RabItem::class);
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return HasMany<PotonganPajak, $this> */
    public function potonganPajak(): HasMany
    {
        return $this->hasMany(PotonganPajak::class);
    }
}
