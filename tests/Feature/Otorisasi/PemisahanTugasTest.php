<?php

use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengembalian\CatatPengembalian;
use App\Actions\Pengembalian\VerifikasiPengembalian;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Actions\Revisi\AjukanRevisiRab;
use App\Actions\Revisi\BuatRevisiRab;
use App\Actions\Revisi\SetujuiRevisiRab;
use App\Actions\Revisi\SimpanItemRevisi;
use App\Actions\Revisi\VerifikasiRevisiRab;
use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\TerimaSpj;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\User;

/**
 * Seluruh kasus BR-07 (pemisahan tugas per pengguna) di satu tempat.
 */
beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran();
});

function gandaPeran(Peran $utama, Peran $tambahan, object $k): User
{
    return penggunaDenganPeran($utama, ['unit_id' => $k->pmat->id])->assignRole($tambahan->value);
}

function revisiDiajukanOleh(object $k, User $pembuat, Pengajuan $p): Rab
{
    $revisi = app(BuatRevisiRab::class)->handle($p, 'Penyesuaian jumlah peserta', $pembuat);
    $item = $revisi->item()->firstOrFail();
    app(SimpanItemRevisi::class)->handle($revisi, [[
        'id' => $item->id, 'akun_id' => $item->akun_id, 'uraian' => $item->uraian, 'volume' => '3', 'satuan' => $item->satuan, 'harga_satuan' => '150000',
    ]], $pembuat);

    return app(AjukanRevisiRab::class)->handle($revisi->fresh(), $pembuat);
}

test('pengajuan: pembuat tidak boleh memverifikasi pengajuannya sendiri', function () {
    $ganda = gandaPeran(Peran::AdminUnit, Peran::KepalaUnit, $this->k);
    $p = app(AjukanPengajuan::class)->handle(buatPengajuanDraf($this->k, oleh: $ganda), $ganda);

    expect(fn () => app(VerifikasiPengajuan::class)->handle($p, $ganda))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('pengajuan: satu orang satu tahap (unit → keuangan)', function () {
    $ganda = gandaPeran(Peran::KepalaUnit, Peran::AdminKeuangan, $this->k);
    $p = app(VerifikasiPengajuan::class)->handle(buatPengajuanDiajukan($this->k), $ganda);

    expect(fn () => app(VerifikasiPengajuan::class)->handle($p, $ganda))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('pengajuan: verifikator keuangan tidak boleh menyetujui tahap akhir', function () {
    $ganda = gandaPeran(Peran::AdminKeuangan, Peran::WadekKeuangan, $this->k);
    $p = app(VerifikasiPengajuan::class)->handle(buatPengajuanDiajukan($this->k), $this->k->kepalaPmat);
    $p = app(VerifikasiPengajuan::class)->handle($p, $ganda);

    expect(fn () => app(SetujuiPengajuan::class)->handle($p, $ganda))->toThrow(PelanggaranPemisahanTugasException::class)
        ->and(app(SetujuiPengajuan::class)->handle($p->fresh(), $this->k->wadek)->status)->toBe(StatusPengajuan::Disetujui);
});

test('revisi: pembuat revisi tidak boleh memverifikasinya', function () {
    $ganda = gandaPeran(Peran::AdminUnit, Peran::AdminKeuangan, $this->k);
    $p = buatPengajuanDisetujui($this->k);
    $revisi = revisiDiajukanOleh($this->k, $ganda, $p);

    expect(fn () => app(VerifikasiRevisiRab::class)->handle($revisi, $ganda))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('revisi: verifikator keuangan tidak boleh menyetujui tahap akhir', function () {
    $ganda = gandaPeran(Peran::AdminKeuangan, Peran::WadekKeuangan, $this->k);
    $revisi = app(VerifikasiRevisiRab::class)->handle(revisiDiajukanOleh($this->k, $this->k->adminPmat, buatPengajuanDisetujui($this->k)), $ganda);

    expect(fn () => app(SetujuiRevisiRab::class)->handle($revisi, $ganda))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('pencairan: penyetuju akhir tidak boleh mencatat pencairan', function () {
    $this->k->wadek->assignRole(Peran::Bpp->value);
    $p = buatPengajuanDisetujui($this->k);
    [$data, $tautan] = dataPencairan('100000');

    expect(fn () => app(CatatPencairan::class)->handle($p, $data, $tautan, $this->k->wadek))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('SPJ: pembuat SPJ tidak boleh menerimanya', function () {
    $this->k->adminPmat->assignRole(Peran::Bpp->value);
    $p = buatPengajuanDicairkan($this->k);
    [$data, $tautan] = dataRealisasi($p, '1', '200000');
    $spj = app(AjukanSpj::class)->handle(app(CatatRealisasi::class)->handle($p, $data, $tautan, $this->k->adminPmat)->spj, $this->k->adminPmat, true);

    expect(fn () => app(TerimaSpj::class)->handle($spj, $this->k->adminPmat))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('pengembalian: pembuat setoran tidak boleh memverifikasinya', function () {
    $this->k->adminPmat->assignRole(Peran::Bpp->value);
    $p = buatPengajuanDicairkan($this->k);
    [$data, $tautan] = dataRealisasi($p, '1', '200000');
    $spj = app(AjukanSpj::class)->handle(app(CatatRealisasi::class)->handle($p, $data, $tautan, $this->k->adminPmat)->spj, $this->k->adminPmat, true);
    app(TerimaSpj::class)->handle($spj, $this->k->keu);
    $setor = app(CatatPengembalian::class)->handle($p->fresh(), ['tanggal_setor' => '2027-06-19', 'jumlah' => '100000', 'nomor_bukti_setor' => 'NTPN-1'], [
        ['label' => 'Bukti setor', 'url' => 'https://drive.google.com/file/d/1SetorPt/view', 'akses_terbatas_dikonfirmasi' => true],
    ], $this->k->adminPmat);

    expect(fn () => app(VerifikasiPengembalian::class)->handle($setor, $this->k->adminPmat))->toThrow(PelanggaranPemisahanTugasException::class)
        ->and(app(VerifikasiPengembalian::class)->handle($setor->fresh(), $this->k->bpp)->status->value)->toBe('diverifikasi');
});
