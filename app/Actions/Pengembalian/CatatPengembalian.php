<?php

namespace App\Actions\Pengembalian;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Berkas\SimpanTautanBerkas;
use App\Enums\JenisTautan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPengembalian;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Setoran pengembalian sisa dana oleh admin-unit (BR-18) dengan tautan bukti setor wajib.
 */
class CatatPengembalian
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly SimpanTautanBerkas $simpanTautan,
    ) {}

    /**
     * @param  array<string, mixed>  $data  tanggal_setor, jumlah, nomor_bukti_setor, keterangan
     * @param  array<int, array<string, mixed>>  $tautanBukti  [label, url, akses_terbatas_dikonfirmasi]
     */
    public function handle(Pengajuan $pengajuan, array $data, array $tautanBukti, User $oleh): Pengembalian
    {
        if (! $oleh->can('create', [Pengembalian::class, $pengajuan])) {
            throw new AuthorizationException('Anda tidak berhak mencatat pengembalian untuk pengajuan ini.');
        }

        $data = Validator::make([...$data, 'tautan_bukti' => $tautanBukti], [
            'tanggal_setor' => ['required', 'date', 'before_or_equal:today'],
            'jumlah' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'nomor_bukti_setor' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'tautan_bukti' => ['required', 'array', 'min:1'],
        ], ['tautan_bukti.required' => 'Tautan bukti setor wajib diisi.', 'tautan_bukti.min' => 'Tautan bukti setor wajib diisi.'])->validate();
        unset($data['tautan_bukti']);

        if (Uang::bandingkan((string) $data['jumlah'], '0') <= 0) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah pengembalian harus lebih dari 0.']);
        }

        return DenganKunci::jalankan("aksi:pengajuan-dana:{$pengajuan->id}", fn (): Pengembalian => DB::transaction(function () use ($pengajuan, $data, $tautanBukti, $oleh): Pengembalian {
            $terkini = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());

            if ($terkini->status !== StatusPengajuan::Dicairkan) {
                throw new TransisiStatusTidakSahException('Pengembalian hanya untuk pengajuan berstatus dicairkan.');
            }

            if (! in_array($terkini->spj()->value('status'), [StatusSpj::Diajukan, StatusSpj::Diterima], true)) {
                throw new KesalahanDomain('Pengembalian dicatat setelah SPJ diajukan.');
            }

            $tertunda = Uang::jumlahkan($terkini->pengembalian()
                ->whereIn('status', [StatusPengembalian::Diajukan->value, StatusPengembalian::Diverifikasi->value])
                ->pluck('jumlah')->map(fn ($j): string => (string) $j)->all());
            $batas = Uang::kurang($terkini->sisaDana(), $tertunda);

            if (Uang::bandingkan((string) $data['jumlah'], $batas) > 0) {
                throw new KesalahanDomain('Jumlah pengembalian melebihi sisa dana yang belum disetor ('.Uang::format($batas).').');
            }

            $pengembalian = new Pengembalian([...$data, 'pengajuan_id' => $terkini->id, 'dibuat_oleh' => $oleh->getKey()]);
            $pengembalian->forceFill(['status' => StatusPengembalian::Diajukan])->save();

            foreach ($tautanBukti as $t) {
                $this->simpanTautan->handle($pengembalian, JenisTautan::BuktiSetor, (string) $t['url'], (string) $t['label'], (bool) ($t['akses_terbatas_dikonfirmasi'] ?? false), $oleh);
            }

            $this->catatRiwayat->handle($pengembalian, null, StatusPengembalian::Diajukan, $oleh);

            return $pengembalian;
        }));
    }
}
