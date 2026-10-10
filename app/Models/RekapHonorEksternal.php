<?php

namespace App\Models;

use App\Concerns\MenjagaPeriodeTerkunci;
use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap total honor ujian dari ekspor fkipapp (BR-24): baca-saja, tanpa rincian per dosen/mahasiswa.
 *
 * @property string $id
 * @property string $batch_impor
 * @property string $sumber
 * @property string $tahun_anggaran_id
 * @property string $periode_id
 * @property string|null $unit_id
 * @property string $jenis_ujian
 * @property int $jumlah_kegiatan
 * @property string $jumlah_bruto
 * @property string $jumlah_pajak
 * @property string $jumlah_neto
 * @property string|null $nama_berkas_sumber
 * @property string $status
 * @property string $diimpor_oleh
 * @property-read Periode $periode
 * @property-read Unit|null $unit
 */
class RekapHonorEksternal extends Model
{
    use HasUuids, MenjagaPeriodeTerkunci, TercatatAktivitas;

    public const JENIS_UJIAN = ['proposal', 'seminar', 'sidang', 'lainnya'];

    protected $table = 'rekap_honor_eksternal';

    protected $attributes = ['status' => 'aktif', 'sumber' => 'fkipapp'];

    protected $fillable = ['batch_impor', 'tahun_anggaran_id', 'periode_id', 'unit_id', 'jenis_ujian', 'jumlah_kegiatan', 'jumlah_bruto', 'jumlah_pajak', 'jumlah_neto', 'nama_berkas_sumber', 'diimpor_oleh'];

    protected function casts(): array
    {
        return [
            'jumlah_kegiatan' => 'integer',
            'jumlah_bruto' => 'decimal:2',
            'jumlah_pajak' => 'decimal:2',
            'jumlah_neto' => 'decimal:2',
        ];
    }

    /**
     * Tanggal acuan periode (awal bulan) untuk guard periode terkunci (BR-19).
     *
     * @return array<int, string>
     */
    protected function kolomTanggalPeriode(): array
    {
        return ['tanggal_periode'];
    }

    /** @return Attribute<string|null, never> */
    protected function tanggalPeriode(): Attribute
    {
        return Attribute::get(fn (): ?string => Periode::query()->whereKey($this->periode_id)->first()?->tanggal_mulai->toDateString());
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
