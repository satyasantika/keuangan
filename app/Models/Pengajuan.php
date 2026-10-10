<?php

namespace App\Models;

use App\Concerns\DibatasiUnit;
use App\Concerns\MemilikiRiwayatStatus;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusPengajuan;
use App\Exceptions\KesalahanDomain;
use Database\Factories\PengajuanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Pengajuan kegiatan beserta RAB berversi.
 *
 * @property string $id
 * @property string|null $nomor
 * @property string $tahun_anggaran_id
 * @property string $unit_id
 * @property string|null $kategori_penggunaan_dana_id
 * @property string $judul_kegiatan
 * @property string|null $deskripsi
 * @property Carbon $tanggal_mulai_kegiatan
 * @property Carbon $tanggal_selesai_kegiatan
 * @property string|null $tempat
 * @property string $penanggung_jawab
 * @property string|null $rab_aktif_id
 * @property StatusPengajuan $status
 * @property bool $wajib_kepala_unit
 * @property bool $wajib_wadek
 * @property string $total_diajukan
 * @property string $total_disetujui
 * @property string $total_dicairkan
 * @property string $total_realisasi
 * @property string $total_pajak
 * @property string $total_dikembalikan
 * @property Carbon|null $batas_spj
 * @property string $dibuat_oleh
 * @property Carbon|null $diajukan_pada
 * @property Carbon|null $disetujui_pada
 * @property Carbon|null $selesai_pada
 * @property string|null $alasan_batal
 * @property-read TahunAnggaran $tahunAnggaran
 * @property-read Unit $unit
 * @property-read KategoriPenggunaanDana|null $kategoriPenggunaanDana
 * @property-read User $pembuat
 * @property-read Rab|null $rabAktif
 * @property-read Rab|null $rabAwal
 */
class Pengajuan extends Model
{
    /** @use HasFactory<PengajuanFactory> */
    use DibatasiUnit, HasFactory, HasUuids, MemilikiRiwayatStatus, SoftDeletes, TercatatAktivitas;

    /** Kolom data inti yang terkunci sejak diajukan (BR-03). */
    public const KOLOM_INTI = [
        'judul_kegiatan', 'deskripsi', 'tanggal_mulai_kegiatan', 'tanggal_selesai_kegiatan', 'tempat',
        'penanggung_jawab', 'unit_id', 'tahun_anggaran_id', 'kategori_penggunaan_dana_id',
    ];

    protected $table = 'pengajuan';

    protected $attributes = [
        'status' => 'draf',
        'wajib_kepala_unit' => true,
        'wajib_wadek' => true,
        'total_diajukan' => '0.00',
        'total_disetujui' => '0.00',
        'total_dicairkan' => '0.00',
        'total_realisasi' => '0.00',
        'total_pajak' => '0.00',
        'total_dikembalikan' => '0.00',
    ];

    /** Status, nomor, snapshot, dan total_* hanya diubah oleh Action. */
    protected $fillable = [
        'tahun_anggaran_id', 'unit_id', 'kategori_penggunaan_dana_id', 'judul_kegiatan', 'deskripsi',
        'tanggal_mulai_kegiatan', 'tanggal_selesai_kegiatan', 'tempat', 'penanggung_jawab', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai_kegiatan' => 'date',
            'tanggal_selesai_kegiatan' => 'date',
            'status' => StatusPengajuan::class,
            'wajib_kepala_unit' => 'boolean',
            'wajib_wadek' => 'boolean',
            'total_diajukan' => 'decimal:2',
            'total_disetujui' => 'decimal:2',
            'total_dicairkan' => 'decimal:2',
            'total_realisasi' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'total_dikembalikan' => 'decimal:2',
            'batas_spj' => 'date',
            'diajukan_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // BR-03: data inti terkunci sejak diajukan; perubahan status/total oleh Action tetap diizinkan.
        static::updating(function (Pengajuan $pengajuan): void {
            $statusLama = StatusPengajuan::from((string) $pengajuan->getRawOriginal('status'));

            if (! $statusLama->dapatDiubah() && $pengajuan->isDirty(self::KOLOM_INTI)) {
                throw new KesalahanDomain('Pengajuan berstatus '.$statusLama->getLabel().' tidak dapat diubah.');
            }
        });
    }

    /** @return BelongsTo<TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<KategoriPenggunaanDana, $this> */
    public function kategoriPenggunaanDana(): BelongsTo
    {
        return $this->belongsTo(KategoriPenggunaanDana::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return HasMany<Rab, $this> */
    public function rab(): HasMany
    {
        return $this->hasMany(Rab::class)->orderBy('nomor_revisi');
    }

    /** @return BelongsTo<Rab, $this> */
    public function rabAktif(): BelongsTo
    {
        return $this->belongsTo(Rab::class, 'rab_aktif_id');
    }

    /** @return HasOne<Rab, $this> */
    public function rabAwal(): HasOne
    {
        return $this->hasOne(Rab::class)->where('nomor_revisi', 0);
    }
}
