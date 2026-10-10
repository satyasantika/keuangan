<?php

use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengembalian\CatatPengembalian;
use App\Actions\Pengembalian\TolakPengembalian;
use App\Actions\Pengembalian\VerifikasiPengembalian;
use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\TerimaSpj;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPengembalian;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Models\Pengajuan;
use App\Models\TautanBerkas;
use App\Models\TautanBerkasBeku;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
});

function dataSetor(string $jumlah): array
{
    return [
        ['tanggal_setor' => '2027-06-19', 'jumlah' => $jumlah, 'nomor_bukti_setor' => 'NTPN-0001'],
        [['label' => 'Bukti setor', 'url' => 'https://drive.google.com/file/d/1Setor'.Str::random(5).'/view', 'akses_terbatas_dikonfirmasi' => true]],
    ];
}

/**
 * RAB 10 jt, dicairkan 10 jt, realisasi $realisasi, SPJ diterima.
 */
function spjDiterima(object $k, string $realisasi): Pengajuan
{
    $p = buatPengajuanDicairkan($k, '1', '10000000');
    [$data, $tautan] = dataRealisasi($p, '1', $realisasi);
    $spj = app(CatatRealisasi::class)->handle($p, $data, $tautan, $k->adminPmat)->spj;
    $spj = app(AjukanSpj::class)->handle($spj, $k->adminPmat, true);
    app(TerimaSpj::class)->handle($spj, $k->keu);

    return $p->fresh();
}

test('skenario lengkap: sisa 250 rb → 300 rb ditolak → 250 rb diverifikasi → selesai, bukti setor dibekukan', function () {
    $p = spjDiterima($this->k, '9750000');

    expect($p->status)->toBe(StatusPengajuan::Dicairkan)
        ->and($p->sisaDana())->toBe('250000.00');

    [$lebih, $tl] = dataSetor('300000');
    expect(fn () => app(CatatPengembalian::class)->handle($p, $lebih, $tl, $this->k->adminPmat))
        ->toThrow(KesalahanDomain::class, 'Rp250.000,00');

    [$pas, $tp] = dataSetor('250000');
    $k = app(CatatPengembalian::class)->handle($p, $pas, $tp, $this->k->adminPmat);
    expect($k->status)->toBe(StatusPengembalian::Diajukan)->and($k->periode_id)->not->toBeNull();

    app(VerifikasiPengembalian::class)->handle($k, $this->k->bpp);
    $p->refresh();

    expect($k->fresh()->status)->toBe(StatusPengembalian::Diverifikasi)
        ->and($p->total_dikembalikan)->toBe('250000.00')
        ->and($p->status)->toBe(StatusPengajuan::Selesai)
        ->and(TautanBerkasBeku::where('pemilik_type', 'pengembalian')->where('pemilik_id', $k->id)->count())->toBe(1);

    $tautan = TautanBerkas::where('pemilik_type', 'pengembalian')->firstOrFail();
    expect(fn () => $tautan->update(['label' => 'x']))->toThrow(KesalahanDomain::class);
});

test('pembuat pengembalian yang juga bpp tidak bisa memverifikasinya sendiri', function () {
    $this->k->adminPmat->assignRole(Peran::Bpp->value);
    $p = spjDiterima($this->k, '9750000');
    [$data, $t] = dataSetor('250000');
    $k = app(CatatPengembalian::class)->handle($p, $data, $t, $this->k->adminPmat);

    expect(fn () => app(VerifikasiPengembalian::class)->handle($k, $this->k->adminPmat))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('tolak pengembalian dengan catatan; jumlah tertunda dihitung', function () {
    $p = spjDiterima($this->k, '9750000');
    [$data, $t] = dataSetor('100000');
    $k = app(CatatPengembalian::class)->handle($p, $data, $t, $this->k->adminPmat);

    [$lagi, $t2] = dataSetor('150000.01');
    expect(fn () => app(CatatPengembalian::class)->handle($p, $lagi, $t2, $this->k->adminPmat))->toThrow(KesalahanDomain::class);

    app(TolakPengembalian::class)->handle($k, $this->k->bpp, 'Nomor bukti setor tidak cocok');
    expect($k->fresh()->status)->toBe(StatusPengembalian::Ditolak)
        ->and($p->fresh()->status)->toBe(StatusPengajuan::Dicairkan);
});

test('kurang bayar → tidak selesai sampai pencairan tambahan dicatat', function () {
    $p = buatPengajuanDisetujui($this->k, '1', '300000');
    [$cair, $tc] = dataPencairan('200000');
    app(CatatPencairan::class)->handle($p, $cair, $tc, $this->k->bpp);

    [$data, $tautan] = dataRealisasi($p->fresh(), '1', '300000');
    $spj = app(CatatRealisasi::class)->handle($p->fresh(), $data, $tautan, $this->k->adminPmat)->spj;
    app(TerimaSpj::class)->handle(app(AjukanSpj::class)->handle($spj, $this->k->adminPmat, true), $this->k->keu);

    $p->refresh();
    expect($p->sisaDana())->toBe('-100000.00')
        ->and($p->status)->toBe(StatusPengajuan::Dicairkan);

    [$tambah, $tt] = dataPencairan('100000', '2027-06-19');
    app(CatatPencairan::class)->handle($p, $tambah, $tt, $this->k->bpp);

    expect($p->fresh()->sisaDana())->toBe('0.00')
        ->and($p->fresh()->status)->toBe(StatusPengajuan::Selesai);
});

test('pengembalian sebelum SPJ diajukan ditolak; admin-unit unit lain tidak berhak', function () {
    $p = buatPengajuanDicairkan($this->k);
    [$data, $t] = dataSetor('1000');

    expect(fn () => app(CatatPengembalian::class)->handle($p, $data, $t, $this->k->adminPmat))->toThrow(KesalahanDomain::class);

    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    expect(fn () => app(CatatPengembalian::class)->handle($p, $data, $t, $adminPbio))->toThrow(AuthorizationException::class);
});
