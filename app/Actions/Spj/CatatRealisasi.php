<?php

namespace App\Actions\Spj;

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Enums\JenisTautan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Events\IndikasiBuktiGandaTerdeteksi;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Catat/ubah realisasi per item RAB aktif dengan tautan bukti wajib (BR-14, BR-15, BR-19).
 */
class CatatRealisasi
{
    /** Tanggal bukti paling awal relatif terhadap mulai kegiatan — kebijakan perlu verifikasi. */
    public const HARI_SEBELUM_KEGIATAN = 30;

    /** @var list<JenisTautan> */
    public const JENIS_BUKTI_UTAMA = [JenisTautan::Kuitansi, JenisTautan::Nota, JenisTautan::Faktur];

    /** @var list<JenisTautan> */
    public const JENIS_DIIZINKAN = [JenisTautan::Kuitansi, JenisTautan::Nota, JenisTautan::Faktur, JenisTautan::DaftarHadir, JenisTautan::FotoKegiatan, JenisTautan::Lainnya];

    private const POLA_DESIMAL = '/^\d+(\.\d{1,2})?$/';

    public function __construct(
        private readonly SimpanTautanBerkas $simpanTautan,
        private readonly HitungUlangTotalPengajuan $hitungUlang,
    ) {}

    /**
     * @param  array<string, mixed>  $data  rab_item_id, tanggal_bukti, nomor_bukti, uraian, penerima, volume, harga_satuan
     * @param  array<int, array<string, mixed>>  $tautan  [jenis, label, url, akses_terbatas_dikonfirmasi]
     */
    public function handle(Pengajuan $pengajuan, array $data, array $tautan, User $oleh, ?RealisasiItem $item = null): RealisasiItem
    {
        $berhak = $item === null
            ? $oleh->can('create', RealisasiItem::class) && $oleh->unit_id === $pengajuan->unit_id
            : $oleh->can('update', $item);

        if (! $berhak) {
            throw new AuthorizationException('Anda tidak berhak mencatat realisasi untuk pengajuan ini.');
        }

        $data = $this->validasi($data, $tautan);

        return DenganKunci::jalankan("aksi:pengajuan-dana:{$pengajuan->id}", fn (): RealisasiItem => DB::transaction(function () use ($pengajuan, $data, $tautan, $oleh, $item): RealisasiItem {
            $terkini = Pengajuan::query()->with('rabAktif')->lockForUpdate()->findOrFail($pengajuan->getKey());

            if ($terkini->status !== StatusPengajuan::Dicairkan) {
                throw new TransisiStatusTidakSahException('Realisasi hanya dapat dicatat untuk pengajuan yang sudah dicairkan.');
            }

            $this->pastikanTanpaRevisiBerjalan($terkini);

            $spj = $terkini->spj()->lockForUpdate()->first();

            if ($spj === null) {
                $spj = new Spj(['pengajuan_id' => $terkini->id, 'dibuat_oleh' => $oleh->getKey()]);
                $spj->save();
            }

            if (! $spj->status->dapatDiubah()) {
                throw new KesalahanDomain('SPJ sudah diajukan atau diterima; realisasi tidak dapat ditambah/diubah.');
            }

            $rabItem = RabItem::query()->whereKey($data['rab_item_id'])->where('rab_id', $terkini->rab_aktif_id)->first();

            if ($rabItem === null) {
                throw new KesalahanDomain('Item RAB harus berasal dari RAB aktif pengajuan.');
            }

            $this->pastikanTanggal((string) $data['tanggal_bukti'], $terkini);

            $bruto = Uang::kali((string) $data['volume'], (string) $data['harga_satuan']);
            $this->pastikanDalamBatas($terkini, $spj, $rabItem, $bruto, $item);

            $realisasi = $item ?? new RealisasiItem(['spj_id' => $spj->id, 'dibuat_oleh' => $oleh->getKey()]);
            $realisasi->fill([...$data, 'spj_id' => $spj->id]);
            $realisasi->forceFill([
                'jumlah_bruto' => $bruto,
                'jumlah_neto' => Uang::kurang($bruto, (string) ($realisasi->total_pajak ?? '0')),
            ])->save();

            foreach ($tautan as $t) {
                $this->simpanTautan->handle($realisasi, JenisTautan::from((string) $t['jenis']), (string) $t['url'], (string) $t['label'], (bool) ($t['akses_terbatas_dikonfirmasi'] ?? false), $oleh);
            }

            if (! $realisasi->tautanJenis(...self::JENIS_BUKTI_UTAMA)->exists()) {
                throw ValidationException::withMessages(['tautan' => 'Realisasi wajib memiliki minimal satu tautan kuitansi, nota, atau faktur.']);
            }

            $this->tandaiBuktiGanda($realisasi, $spj);
            $this->hitungUlang->handle($terkini);

            return $realisasi;
        }));
    }

    private function pastikanTanpaRevisiBerjalan(Pengajuan $pengajuan): void
    {
        $berjalan = Rab::query()
            ->where('pengajuan_id', $pengajuan->id)
            ->where('nomor_revisi', '>', 0)
            ->whereIn('status', [StatusRab::Draf->value, StatusRab::Diajukan->value, StatusRab::DiverifikasiKeuangan->value])
            ->exists();

        if ($berjalan) {
            throw new KesalahanDomain('Masih ada revisi RAB yang berjalan; selesaikan revisi terlebih dahulu.');
        }
    }

    private function pastikanTanggal(string $tanggal, Pengajuan $pengajuan): void
    {
        $tgl = Carbon::parse($tanggal)->startOfDay();
        $palingAwal = $pengajuan->tanggal_mulai_kegiatan->copy()->subDays(self::HARI_SEBELUM_KEGIATAN);

        if ($tgl->lt($palingAwal)) {
            throw ValidationException::withMessages(['tanggal_bukti' => 'Tanggal bukti paling awal '.self::HARI_SEBELUM_KEGIATAN.' hari sebelum kegiatan dimulai ('.$palingAwal->format('d-m-Y').').']);
        }
    }

    /**
     * BR-14: Σ realisasi per item ≤ jumlah item RAB aktif; Σ per pengajuan ≤ total RAB aktif.
     */
    private function pastikanDalamBatas(Pengajuan $pengajuan, Spj $spj, RabItem $rabItem, string $bruto, ?RealisasiItem $kecuali): void
    {
        $lain = RealisasiItem::query()->where('spj_id', $spj->id)->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id));

        $perItem = Uang::jumlahkan((clone $lain)->where('rab_item_id', $rabItem->id)->pluck('jumlah_bruto')->map(fn ($j): string => (string) $j)->all());

        if (Uang::bandingkan(Uang::jumlahkan([$perItem, $bruto]), (string) $rabItem->jumlah) > 0) {
            throw new KesalahanDomain("Realisasi melebihi jumlah item \"{$rabItem->uraian}\". Sisa item ".Uang::format(Uang::kurang((string) $rabItem->jumlah, $perItem)).'.');
        }

        $semua = Uang::jumlahkan((clone $lain)->pluck('jumlah_bruto')->map(fn ($j): string => (string) $j)->all());
        $totalRab = (string) ($pengajuan->rabAktif->total ?? '0');

        if (Uang::bandingkan(Uang::jumlahkan([$semua, $bruto]), $totalRab) > 0) {
            throw new KesalahanDomain('Total realisasi melebihi total RAB aktif. Sisa '.Uang::format(Uang::kurang($totalRab, $semua)).'.');
        }
    }

    /**
     * BR-15: bukti yang sama (drive_file_id/URL) pada realisasi pengajuan lain → tandai, tidak memblokir.
     */
    private function tandaiBuktiGanda(RealisasiItem $realisasi, Spj $spj): void
    {
        $milikSendiri = RealisasiItem::withTrashed()->where('spj_id', $spj->id)->pluck('id');
        $lain = [];

        foreach ($realisasi->tautanBerkas()->get() as $tautan) {
            $lain = [...$lain, ...TautanBerkas::query()
                ->where('pemilik_type', $realisasi->getMorphClass())
                ->whereNotIn('pemilik_id', $milikSendiri)
                ->where(fn ($q) => $tautan->drive_file_id !== null
                    ? $q->where('drive_file_id', $tautan->drive_file_id)
                    : $q->where('url', $tautan->url))
                ->pluck('pemilik_id')
                ->all()];
        }

        $lain = array_values(array_unique($lain));

        if ($lain !== [] && ! $realisasi->indikasi_bukti_ganda) {
            $realisasi->forceFill(['indikasi_bukti_ganda' => true])->save();
            IndikasiBuktiGandaTerdeteksi::dispatch($realisasi, $lain);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $tautan
     * @return array<string, mixed>
     */
    private function validasi(array $data, array $tautan): array
    {
        $jenis = implode(',', array_map(fn (JenisTautan $j): string => $j->value, self::JENIS_DIIZINKAN));

        $hasil = Validator::make(
            [...$data, 'tautan' => $tautan],
            [
                'rab_item_id' => ['required', 'string'],
                'tanggal_bukti' => ['required', 'date', 'before_or_equal:today'],
                'nomor_bukti' => ['required', 'string', 'max:100'],
                'uraian' => ['required', 'string', 'max:255'],
                'penerima' => ['nullable', 'string', 'max:150'],
                'volume' => ['required', 'regex:'.self::POLA_DESIMAL],
                'harga_satuan' => ['required', 'regex:'.self::POLA_DESIMAL],
                'tautan' => ['array'],
                'tautan.*.jenis' => ['required', 'in:'.$jenis],
                'tautan.*.label' => ['required', 'string', 'max:150'],
                'tautan.*.url' => ['required', 'string'],
            ],
            ['tanggal_bukti.before_or_equal' => 'Tanggal bukti tidak boleh melewati hari ini.'],
        )->validate();

        if (Uang::bandingkan((string) $hasil['volume'], '0') <= 0) {
            throw ValidationException::withMessages(['volume' => 'Volume harus lebih dari 0.']);
        }

        unset($hasil['tautan']);

        return $hasil;
    }
}
