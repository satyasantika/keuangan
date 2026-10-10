<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Exceptions\KesalahanDomain;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tarif pajak REFERENSI (BR-16): sistem tidak menghitung pajak; tarif hanya disalin sebagai snapshot.
 *
 * @property string $id
 * @property string $jenis_pajak_id
 * @property string $uraian
 * @property string $tarif_persen
 * @property Carbon $berlaku_mulai
 * @property Carbon|null $berlaku_sampai
 * @property string|null $dasar_hukum
 * @property bool $perlu_verifikasi
 * @property bool $aktif
 * @property string|null $keterangan
 * @property-read JenisPajak $jenisPajak
 */
class TarifPajak extends Model
{
    use HasUuids, TercatatAktivitas;

    public const KUNCI_VERSI_CACHE = 'master:tarif-pajak:versi';

    protected $table = 'tarif_pajak';

    protected $attributes = ['perlu_verifikasi' => true, 'aktif' => true];

    protected $fillable = ['jenis_pajak_id', 'uraian', 'tarif_persen', 'berlaku_mulai', 'berlaku_sampai', 'dasar_hukum', 'perlu_verifikasi', 'aktif', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tarif_persen' => 'decimal:2',
            'berlaku_mulai' => 'date',
            'berlaku_sampai' => 'date',
            'perlu_verifikasi' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TarifPajak $tarif): void {
            if ($tarif->berlaku_sampai !== null && $tarif->berlaku_sampai->lt($tarif->berlaku_mulai)) {
                throw new KesalahanDomain('Tanggal berlaku sampai tidak boleh sebelum berlaku mulai.');
            }

            if (self::tumpangTindih($tarif->jenis_pajak_id, $tarif->uraian, $tarif->berlaku_mulai, $tarif->berlaku_sampai, $tarif->exists ? $tarif->id : null)) {
                throw new KesalahanDomain("Rentang berlaku tarif \"{$tarif->uraian}\" tumpang tindih dengan tarif lain.");
            }
        });

        $naikkanVersi = fn () => Cache::forever(self::KUNCI_VERSI_CACHE, (int) Cache::get(self::KUNCI_VERSI_CACHE, 0) + 1);

        static::saved($naikkanVersi);
        static::deleted($naikkanVersi);
    }

    /** @return BelongsTo<JenisPajak, $this> */
    public function jenisPajak(): BelongsTo
    {
        return $this->belongsTo(JenisPajak::class);
    }

    /**
     * Apakah rentang [mulai, sampai] (sampai null = tanpa batas) bertumpang tindih dengan tarif aktif
     * lain berjenis & beruraian sama.
     */
    public static function tumpangTindih(string $jenisPajakId, string $uraian, CarbonInterface|string $mulai, CarbonInterface|string|null $sampai, ?string $kecualiId = null): bool
    {
        $mulai = Carbon::parse($mulai)->toDateString();
        $sampai = $sampai === null ? null : Carbon::parse($sampai)->toDateString();

        return self::query()
            ->where('jenis_pajak_id', $jenisPajakId)
            ->where('uraian', $uraian)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->when($sampai, fn ($q) => $q->whereDate('berlaku_mulai', '<=', $sampai))
            ->where(fn ($q) => $q->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $mulai))
            ->exists();
    }

    /**
     * Tarif referensi aktif yang berlaku pada tanggal tertentu, terbaru dahulu.
     */
    public static function berlakuPada(JenisPajak $jenis, CarbonInterface $tanggal): ?self
    {
        $tgl = $tanggal->toDateString();
        $versi = (int) Cache::get(self::KUNCI_VERSI_CACHE, 0);

        $id = Cache::remember("master:tarif-pajak:v{$versi}:{$jenis->id}:{$tgl}", now()->addDay(), fn (): string => (string) self::query()
            ->where('jenis_pajak_id', $jenis->id)
            ->where('aktif', true)
            ->whereDate('berlaku_mulai', '<=', $tgl)
            ->where(fn ($q) => $q->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $tgl))
            ->orderByDesc('berlaku_mulai')
            ->value('id'));

        return $id === '' ? null : self::find($id);
    }
}
