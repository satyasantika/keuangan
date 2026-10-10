<?php

namespace App\Models;

use App\Enums\StatusPeriode;
use App\Exceptions\KesalahanDomain;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tahun_anggaran_id
 * @property int $bulan
 * @property string $nama
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property StatusPeriode $status
 * @property string|null $dikunci_oleh
 * @property Carbon|null $dikunci_pada
 * @property int $versi_kunci
 * @property-read TahunAnggaran $tahunAnggaran
 */
class Periode extends Model
{
    use HasUuids;

    protected $table = 'periode';

    protected $attributes = ['status' => 'terbuka', 'versi_kunci' => 0];

    /** Status, penguncian, dan versi kunci hanya diubah oleh Action (F9). */
    protected $fillable = ['tahun_anggaran_id', 'bulan', 'nama', 'tanggal_mulai', 'tanggal_selesai'];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status' => StatusPeriode::class,
            'dikunci_pada' => 'datetime',
            'versi_kunci' => 'integer',
        ];
    }

    /** @return BelongsTo<TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /**
     * Periode yang memuat tanggal; null bila tidak ada.
     */
    public static function cariUntukTanggal(CarbonInterface|string $tanggal): ?self
    {
        $tanggal = Carbon::parse($tanggal)->toDateString();

        return static::query()
            ->with('tahunAnggaran')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();
    }

    /**
     * @throws KesalahanDomain bila tidak ada periode untuk tanggal itu
     */
    public static function untukTanggal(CarbonInterface|string $tanggal): self
    {
        return static::cariUntukTanggal($tanggal)
            ?? throw new KesalahanDomain('Tidak ada periode untuk tanggal '.Carbon::parse($tanggal)->format('d-m-Y').'.');
    }
}
