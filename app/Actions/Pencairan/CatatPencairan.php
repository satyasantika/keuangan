<?php

namespace App\Actions\Pencairan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Enums\JenisTautan;
use App\Enums\StatusPencairan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTahunAnggaran;
use App\Enums\TahapPersetujuan;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pencairan;
use App\Models\Pengajuan;
use App\Models\Periode;
use App\Models\RiwayatStatus;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Pengaturan;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan pencairan oleh BPP (BR-12, BR-13, BR-19, BR-07c, BR-26).
 */
class CatatPencairan
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly SimpanTautanBerkas $simpanTautan,
        private readonly HitungUlangTotalPengajuan $hitungUlang,
    ) {}

    /**
     * @param  array<string, mixed>  $data  jenis_pencairan_id, tanggal, jumlah, nomor_bukti, penerima, keterangan
     * @param  array<int, array<string, mixed>>  $tautanBukti  [label, url, akses_terbatas_dikonfirmasi]
     */
    public function handle(Pengajuan $pengajuan, array $data, array $tautanBukti, User $oleh): Pencairan
    {
        if (! $oleh->can('create', [Pencairan::class, $pengajuan])) {
            throw new AuthorizationException('Hanya bendahara (BPP) yang dapat mencatat pencairan.');
        }

        $data = $this->validasi($data, $tautanBukti);

        return DenganKunci::jalankan("aksi:pengajuan-dana:{$pengajuan->id}", fn (): Pencairan => DB::transaction(function () use ($pengajuan, $data, $tautanBukti, $oleh): Pencairan {
            $terkini = Pengajuan::query()->with(['tahunAnggaran', 'rabAktif'])->lockForUpdate()->findOrFail($pengajuan->getKey());

            if (! in_array($terkini->status, [StatusPengajuan::Disetujui, StatusPengajuan::Dicairkan], true)) {
                throw new TransisiStatusTidakSahException('Pencairan hanya untuk pengajuan berstatus disetujui atau dicairkan.');
            }

            $this->pastikanPemisahanTugas($terkini, $oleh);
            $this->pastikanTanggal((string) $data['tanggal'], $terkini);

            $sudahCair = $terkini->pencairan()->where('status', StatusPencairan::Dicatat->value)->pluck('jumlah')->map(fn ($j): string => (string) $j)->all();
            $totalBaru = Uang::jumlahkan([...$sudahCair, (string) $data['jumlah']]);
            $totalRab = (string) ($terkini->rabAktif->total ?? '0');

            if (Uang::bandingkan($totalBaru, $totalRab) > 0) {
                throw new KesalahanDomain('Jumlah pencairan melebihi total RAB aktif. Sisa yang dapat dicairkan '.Uang::format(Uang::kurang($totalRab, Uang::jumlahkan($sudahCair))).'.');
            }

            $pencairanPertama = $sudahCair === [];

            $pencairan = new Pencairan([...$data, 'pengajuan_id' => $terkini->id]);
            $pencairan->forceFill(['status' => StatusPencairan::Dicatat, 'dicatat_oleh' => $oleh->getKey()])->save();

            foreach ($tautanBukti as $tautan) {
                $this->simpanTautan->handle($pencairan, JenisTautan::BuktiPencairan, (string) $tautan['url'], (string) $tautan['label'], (bool) ($tautan['akses_terbatas_dikonfirmasi'] ?? false), $oleh);
            }

            $this->catatRiwayat->handle($pencairan, null, StatusPencairan::Dicatat, $oleh, null, TahapPersetujuan::Bendahara);

            if ($pencairanPertama && $terkini->status === StatusPengajuan::Disetujui) {
                $batas = Carbon::parse(max($terkini->tanggal_selesai_kegiatan->toDateString(), $pencairan->tanggal->toDateString()))
                    ->addDays((int) Pengaturan::get('batas_hari_spj', 14));

                $terkini->forceFill(['status' => StatusPengajuan::Dicairkan, 'batas_spj' => $batas->toDateString()])->save();
                $this->catatRiwayat->handle($terkini, StatusPengajuan::Disetujui, StatusPengajuan::Dicairkan, $oleh, 'Pencairan pertama dicatat', TahapPersetujuan::Bendahara);
            }

            $this->hitungUlang->handle($terkini);

            return $pencairan;
        }));
    }

    /**
     * BR-07(c): pencatat ≠ penyetuju akhir pengajuan, dan bukan pembuatnya.
     */
    private function pastikanPemisahanTugas(Pengajuan $pengajuan, User $oleh): void
    {
        $penyetujuAkhir = RiwayatStatus::query()
            ->where('model_type', $pengajuan->getMorphClass())
            ->where('model_id', $pengajuan->getKey())
            ->where('ke', StatusPengajuan::Disetujui->value)
            ->where('user_id', $oleh->getKey())
            ->exists();

        if ($penyetujuAkhir || $pengajuan->dibuat_oleh === $oleh->getKey()) {
            throw new PelanggaranPemisahanTugasException('Pemisahan tugas: pencatat pencairan tidak boleh penyetuju akhir atau pembuat pengajuan.');
        }
    }

    private function pastikanTanggal(string $tanggal, Pengajuan $pengajuan): void
    {
        $periode = Periode::untukTanggal($tanggal);

        if ($periode->tahun_anggaran_id !== $pengajuan->tahun_anggaran_id || $pengajuan->tahunAnggaran->status !== StatusTahunAnggaran::Berjalan) {
            throw new KesalahanDomain('Tanggal pencairan harus berada pada tahun anggaran pengajuan yang sedang berjalan.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $tautanBukti
     * @return array<string, mixed>
     */
    private function validasi(array $data, array $tautanBukti): array
    {
        $hasil = Validator::make(
            [...$data, 'tautan_bukti' => $tautanBukti],
            [
                'jenis_pencairan_id' => ['required', 'string', Rule::exists('jenis_pencairan', 'id')->where('aktif', true)->whereNull('deleted_at')],
                'tanggal' => ['required', 'date', 'before_or_equal:today'],
                'jumlah' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
                'nomor_bukti' => ['required', 'string', 'max:100'],
                'penerima' => ['nullable', 'string', 'max:150'],
                'keterangan' => ['nullable', 'string', 'max:255'],
                'tautan_bukti' => ['required', 'array', 'min:1'],
            ],
            [
                'tautan_bukti.required' => 'Tautan bukti pencairan wajib diisi.',
                'tautan_bukti.min' => 'Tautan bukti pencairan wajib diisi.',
                'tanggal.before_or_equal' => 'Tanggal pencairan tidak boleh melewati hari ini.',
                'jumlah.regex' => 'Jumlah harus angka desimal positif.',
            ],
        )->validate();

        if (Uang::bandingkan((string) $hasil['jumlah'], '0') <= 0) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah pencairan harus lebih dari 0.']);
        }

        unset($hasil['tautan_bukti']);

        return $hasil;
    }
}
