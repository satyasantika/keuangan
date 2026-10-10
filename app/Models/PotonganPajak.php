<?php

namespace App\Models;

use App\Concerns\MenjagaPeriodeTerkunci;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use Database\Factories\PotonganPajakFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Potongan pajak sebagai INFORMASI (BR-16): nilai diisi pengguna, tarif hanya referensi tersalin.
 *
 * @property string $id
 * @property string $realisasi_item_id
 * @property string $jenis_pajak_id
 * @property string|null $tarif_pajak_id
 * @property string|null $tarif_referensi
 * @property string $dasar_pengenaan
 * @property string $nilai
 * @property string|null $nomor_bukti_setor
 * @property Carbon|null $tanggal_setor
 * @property string|null $keterangan
 * @property string $dicatat_oleh
 * @property-read RealisasiItem $realisasiItem
 * @property-read JenisPajak $jenisPajak
 */
class PotonganPajak extends Model
{
    /** @use HasFactory<PotonganPajakFactory> */
    use HasFactory, HasUuids, MenjagaPeriodeTerkunci, TercatatAktivitas;

    /** BPP boleh melengkapi bukti setor saat SPJ diajukan; setelah diterima terkunci. */
    public const STATUS_SPJ_TERBUKA = [StatusSpj::Draf, StatusSpj::Dikembalikan, StatusSpj::Diajukan];

    protected $table = 'potongan_pajak';

    protected $fillable = ['realisasi_item_id', 'jenis_pajak_id', 'dasar_pengenaan', 'nilai', 'nomor_bukti_setor', 'tanggal_setor', 'keterangan', 'dicatat_oleh'];

    protected function casts(): array
    {
        return [
            'tarif_referensi' => 'decimal:2',
            'dasar_pengenaan' => 'decimal:2',
            'nilai' => 'decimal:2',
            'tanggal_setor' => 'date',
        ];
    }

    protected static function booted(): void
    {
        $jaga = function (PotonganPajak $potongan): void {
            if (! in_array($potongan->statusSpj(), self::STATUS_SPJ_TERBUKA, true)) {
                throw new KesalahanDomain('Potongan pajak terkunci: SPJ sudah diterima.');
            }
        };

        static::saving($jaga);
        static::deleting($jaga);
    }

    /**
     * @return array<int, string>
     */
    protected function kolomTanggalPeriode(): array
    {
        return ['tanggal_setor'];
    }

    public function statusSpj(): ?StatusSpj
    {
        $status = RealisasiItem::withTrashed()
            ->whereKey($this->realisasi_item_id)
            ->join('spj', 'spj.id', '=', 'realisasi_item.spj_id')
            ->toBase()
            ->value('spj.status');

        return $status === null ? null : StatusSpj::from((string) $status);
    }

    /** @return BelongsTo<RealisasiItem, $this> */
    public function realisasiItem(): BelongsTo
    {
        return $this->belongsTo(RealisasiItem::class);
    }

    /** @return BelongsTo<JenisPajak, $this> */
    public function jenisPajak(): BelongsTo
    {
        return $this->belongsTo(JenisPajak::class);
    }

    /** @return BelongsTo<TarifPajak, $this> */
    public function tarifPajak(): BelongsTo
    {
        return $this->belongsTo(TarifPajak::class);
    }
}
