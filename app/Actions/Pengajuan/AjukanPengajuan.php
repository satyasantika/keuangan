<?php

namespace App\Actions\Pengajuan;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Enums\JenisTautan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusRab;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PaguTidakCukupException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Akun;
use App\Models\Pagu;
use App\Models\PaguAkun;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Pengaturan;
use App\Support\Penomoran;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * draf/dikembalikan → diajukan (BR-04, BR-05, BR-06 snapshot, BR-08, BR-09).
 */
class AjukanPengajuan
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly HitungSisaPagu $hitungSisaPagu,
    ) {}

    public function handle(Pengajuan $pengajuan, User $oleh): Pengajuan
    {
        if (! $oleh->can('update', $pengajuan)) {
            throw new AuthorizationException('Anda tidak berhak mengajukan pengajuan ini.');
        }

        $tahun = (int) $pengajuan->tahunAnggaran()->value('tahun');

        return DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", fn (): Pengajuan => DenganKunci::jalankan(
            "pagu:{$tahun}:{$pengajuan->unit_id}",
            fn (): Pengajuan => DB::transaction(fn (): Pengajuan => $this->ajukan($pengajuan, $tahun, $oleh)),
        ));
    }

    private function ajukan(Pengajuan $pengajuan, int $tahun, User $oleh): Pengajuan
    {
        $terkini = Pengajuan::query()->with('tahunAnggaran')->lockForUpdate()->findOrFail($pengajuan->getKey());
        $dari = $terkini->status;

        if (! $dari->dapatDiubah() || ! $dari->bolehBerpindahKe(StatusPengajuan::Diajukan)) {
            throw new TransisiStatusTidakSahException('Hanya pengajuan berstatus draf atau dikembalikan yang dapat diajukan.');
        }

        if ($terkini->tahunAnggaran->status !== StatusTahunAnggaran::Berjalan) {
            throw new KesalahanDomain("Tahun anggaran {$tahun} belum berjalan; pengajuan belum dapat diajukan.");
        }

        $pagu = Pagu::berlakuUntuk($terkini->tahun_anggaran_id, $terkini->unit_id);

        if ($pagu === null) {
            throw new PaguTidakCukupException('Pagu unit untuk tahun anggaran ini belum ditetapkan.');
        }

        $rab = $terkini->rabAwal()->lockForUpdate()->first();

        if ($rab === null || ! $rab->item()->exists()) {
            throw new KesalahanDomain('RAB wajib memiliki minimal satu item.');
        }

        if (! $terkini->tautanJenis(JenisTautan::Tor)->exists()) {
            throw new KesalahanDomain('Tautan TOR/KAK wajib ditambahkan sebelum mengajukan.');
        }

        $this->pastikanPaguCukup($terkini, $rab);

        $nomorBaru = $terkini->nomor === null;
        $nomor = $terkini->nomor ?? Penomoran::berikutnya('pengajuan', $tahun, (string) Pengaturan::get('format_nomor_pengajuan', 'PGJ/{tahun}/{urut}'));

        $terkini->forceFill([
            'nomor' => $nomor,
            'status' => StatusPengajuan::Diajukan,
            'wajib_kepala_unit' => (bool) Pengaturan::get('wajib_verifikasi_kepala_unit', true),
            'wajib_wadek' => (bool) Pengaturan::get('wajib_persetujuan_wadek', true),
            'diajukan_pada' => now(),
        ])->save();

        $statusRabLama = $rab->status;
        $rab->forceFill(['status' => StatusRab::Diajukan, 'diajukan_pada' => now()])->save();

        $this->catatRiwayat->handle($terkini, $dari, StatusPengajuan::Diajukan, $oleh, $nomorBaru ? 'Diajukan' : 'Diajukan ulang');
        $this->catatRiwayat->handle($rab, $statusRabLama, StatusRab::Diajukan, $oleh, 'RAB awal diajukan bersama pengajuan');

        return $terkini;
    }

    /**
     * BR-04: total ≤ sisa pagu unit, dan per akun ≤ sisa rincian pagu akun bila ada.
     */
    private function pastikanPaguCukup(Pengajuan $pengajuan, Rab $rab): void
    {
        $sisa = $this->hitungSisaPagu->handle($pengajuan->tahun_anggaran_id, $pengajuan->unit_id, kecualiPengajuanId: $pengajuan->id);
        $total = (string) $pengajuan->total_diajukan;

        if ($sisa === null || Uang::bandingkan($total, $sisa['sisa']) > 0) {
            throw new PaguTidakCukupException('Total pengajuan '.Uang::format($total).' melebihi sisa pagu unit '.Uang::format($sisa['sisa'] ?? '0').'.');
        }

        $perAkun = [];

        /** @var RabItem $item */
        foreach ($rab->item()->get() as $item) {
            $perAkun[$item->akun_id][] = (string) $item->jumlah;
        }

        $akunBerincian = PaguAkun::query()
            ->whereHas('pagu', fn ($q) => $q->where('tahun_anggaran_id', $pengajuan->tahun_anggaran_id)->where('unit_id', $pengajuan->unit_id))
            ->whereIn('akun_id', array_keys($perAkun))
            ->pluck('akun_id')
            ->all();

        foreach ($akunBerincian as $akunId) {
            $sisaAkun = $this->hitungSisaPagu->handle($pengajuan->tahun_anggaran_id, $pengajuan->unit_id, $akunId, $pengajuan->id);
            $jumlahAkun = Uang::jumlahkan($perAkun[$akunId]);

            if ($sisaAkun !== null && Uang::bandingkan($jumlahAkun, $sisaAkun['sisa']) > 0) {
                $kode = Akun::query()->whereKey($akunId)->value('kode');

                throw new PaguTidakCukupException("Jumlah akun {$kode} ".Uang::format($jumlahAkun).' melebihi sisa pagu akun '.Uang::format($sisaAkun['sisa']).'.');
            }
        }
    }
}
