<?php

namespace App\Actions\Spj;

use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use App\Models\JenisPajak;
use App\Models\PotonganPajak;
use App\Models\RealisasiItem;
use App\Models\TarifPajak;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Potongan pajak dicatat sebagai INFORMASI (BR-16): nilai diisi pengguna sesuai bukti potong.
 * Sistem hanya menyalin tarif referensi yang berlaku; tidak pernah menghitung nilai pajak.
 */
class CatatPotonganPajak
{
    /** Kolom yang boleh dilengkapi BPP saat SPJ sudah diajukan. */
    public const KOLOM_BUKTI_SETOR = ['nomor_bukti_setor', 'tanggal_setor', 'keterangan'];

    private const POLA_DESIMAL = '/^\d+(\.\d{1,2})?$/';

    public function __construct(private readonly HitungUlangTotalPengajuan $hitungUlang) {}

    /**
     * @param  array<string, mixed>  $data  jenis_pajak_id, dasar_pengenaan, nilai, nomor_bukti_setor, tanggal_setor, keterangan
     */
    public function handle(RealisasiItem $realisasi, array $data, User $oleh, ?PotonganPajak $potongan = null): PotonganPajak
    {
        $berhak = $potongan === null ? $oleh->can('create', [PotonganPajak::class, $realisasi]) : $oleh->can('update', $potongan);

        if (! $berhak) {
            throw new AuthorizationException('Anda tidak berhak mencatat potongan pajak ini.');
        }

        $data = $this->validasi($data, $realisasi);
        $pengajuan = $realisasi->spj->pengajuan;

        return DenganKunci::jalankan("aksi:pengajuan-dana:{$pengajuan->id}", fn (): PotonganPajak => DB::transaction(function () use ($realisasi, $data, $oleh, $potongan, $pengajuan): PotonganPajak {
            $statusSpj = $realisasi->spj()->lockForUpdate()->firstOrFail()->status;

            if ($statusSpj === StatusSpj::Diajukan) {
                return $this->lengkapiBuktiSetor($potongan, $data);
            }

            $jenis = JenisPajak::query()->findOrFail($data['jenis_pajak_id']);
            $tarif = TarifPajak::berlakuPada($jenis, $realisasi->tanggal_bukti);

            $potongan ??= new PotonganPajak(['realisasi_item_id' => $realisasi->id, 'dicatat_oleh' => $oleh->getKey()]);
            $potongan->fill($data);
            // Snapshot tarif referensi; nilai TIDAK dihitung dari tarif.
            $potongan->forceFill([
                'tarif_pajak_id' => $tarif?->id,
                'tarif_referensi' => $tarif?->tarif_persen,
            ])->save();

            $this->perbaruiRealisasi($realisasi);
            $this->hitungUlang->handle($pengajuan);

            return $potongan;
        }));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lengkapiBuktiSetor(?PotonganPajak $potongan, array $data): PotonganPajak
    {
        if ($potongan === null) {
            throw new KesalahanDomain('SPJ sudah diajukan; potongan pajak baru tidak dapat ditambahkan.');
        }

        $berubah = $potongan->jenis_pajak_id !== (string) $data['jenis_pajak_id']
            || Uang::bandingkan((string) $potongan->dasar_pengenaan, (string) $data['dasar_pengenaan']) !== 0
            || Uang::bandingkan((string) $potongan->nilai, (string) $data['nilai']) !== 0;

        if ($berubah) {
            throw new KesalahanDomain('SPJ sudah diajukan; hanya nomor/tanggal bukti setor dan keterangan yang dapat dilengkapi.');
        }

        $potongan->fill(array_intersect_key($data, array_flip(self::KOLOM_BUKTI_SETOR)))->save();

        return $potongan;
    }

    public function perbaruiRealisasi(RealisasiItem $realisasi): void
    {
        $totalPajak = Uang::jumlahkan($realisasi->potonganPajak()->pluck('nilai')->map(fn ($n): string => (string) $n)->all());

        $realisasi->forceFill([
            'total_pajak' => $totalPajak,
            'jumlah_neto' => Uang::kurang((string) $realisasi->jumlah_bruto, $totalPajak),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validasi(array $data, RealisasiItem $realisasi): array
    {
        $hasil = Validator::make($data, [
            'jenis_pajak_id' => ['required', 'string', Rule::exists('jenis_pajak', 'id')->where('aktif', true)->whereNull('deleted_at')],
            'dasar_pengenaan' => ['required', 'regex:'.self::POLA_DESIMAL],
            'nilai' => ['required', 'regex:'.self::POLA_DESIMAL],
            'nomor_bukti_setor' => ['nullable', 'string', 'max:100'],
            'tanggal_setor' => ['nullable', 'date', 'before_or_equal:today'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $dasar = (string) $hasil['dasar_pengenaan'];
        $nilai = (string) $hasil['nilai'];

        if (Uang::bandingkan($dasar, '0') <= 0 || Uang::bandingkan($dasar, (string) $realisasi->jumlah_bruto) > 0) {
            throw ValidationException::withMessages(['dasar_pengenaan' => 'Dasar pengenaan harus lebih dari 0 dan tidak melebihi jumlah bruto realisasi ('.Uang::format((string) $realisasi->jumlah_bruto).').']);
        }

        if (Uang::bandingkan($nilai, $dasar) > 0) {
            throw ValidationException::withMessages(['nilai' => 'Nilai pajak tidak boleh melebihi dasar pengenaan.']);
        }

        return $hasil;
    }
}
