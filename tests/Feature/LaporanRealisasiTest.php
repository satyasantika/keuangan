<?php

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Master\TetapkanPagu;
use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Actions\Periode\KunciPeriode;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Actions\Spj\CatatRealisasi;
use App\Data\ParameterLaporan;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Models\Pagu;
use App\Models\Periode;
use App\Queries\LaporanRealisasiQuery;
use App\Queries\SpjTerlambatQuery;
use App\Support\VersiCacheDasbor;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo('2027-03-01 10:00:00');
    $this->k = siapkanAnggaran('10000000.00');

    // PMAT: disetujui 1 jt, cair 5 Maret 1 jt, realisasi Maret 400 rb + April 300 rb.
    $a = buatPengajuanDisetujui($this->k, '2', '500000', ['tanggal_mulai_kegiatan' => '2027-03-10', 'tanggal_selesai_kegiatan' => '2027-03-11']);
    $this->travelTo('2027-03-05 10:00:00');
    [$cair, $bukti] = dataPencairan('1000000', '2027-03-05');
    app(CatatPencairan::class)->handle($a, $cair, $bukti, $this->k->bpp);
    $this->travelTo('2027-04-03 10:00:00');
    foreach ([['400000', '2027-03-12'], ['300000', '2027-04-02']] as [$n, $tgl]) {
        [$d, $t] = dataRealisasi($a->fresh(), '1', $n, tanggal: $tgl);
        app(CatatRealisasi::class)->handle($a->fresh(), $d, $t, $this->k->adminPmat);
    }

    // PBIO: pagu 5 jt, disetujui 500 rb, cair Juli 500 rb, realisasi Juli 200 rb.
    $this->travelTo('2027-07-12 10:00:00');
    $paguPbio = Pagu::factory()->create(['tahun_anggaran_id' => $this->k->tahun->id, 'unit_id' => $this->k->pbio->id, 'jumlah' => '5000000.00', 'dibuat_oleh' => $this->k->keu->id]);
    app(TetapkanPagu::class)->handle($paguPbio, $this->k->wadek);
    $this->adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    $kepalaPbio = penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $this->k->pbio->id]);
    $b = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($this->k->tahun->id, ['tanggal_mulai_kegiatan' => '2027-07-10', 'tanggal_selesai_kegiatan' => '2027-07-11']), [itemRab($this->k->akun->id, '1', '500000')], $this->adminPbio);
    app(SimpanTautanBerkas::class)->handle($b, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorPbio/view', 'TOR', false, $this->adminPbio);
    $b = app(AjukanPengajuan::class)->handle($b->fresh(), $this->adminPbio);
    $b = app(VerifikasiPengajuan::class)->handle($b, $kepalaPbio);
    $b = app(VerifikasiPengajuan::class)->handle($b, $this->k->keu);
    $b = app(SetujuiPengajuan::class)->handle($b, $this->k->wadek)->fresh();
    [$cair2, $bukti2] = dataPencairan('500000', '2027-07-12');
    app(CatatPencairan::class)->handle($b, $cair2, $bukti2, $this->k->bpp);
    $this->travelTo('2027-08-01 10:00:00');
    [$d2, $t2] = dataRealisasi($b->fresh(), '1', '200000', tanggal: '2027-07-13');
    app(CatatRealisasi::class)->handle($b->fresh(), $d2, $t2, $this->adminPbio);

    $this->pimpinan = penggunaDenganPeran(Peran::Pimpinan);
    $this->param = fn (string $jenis, ?int $nilai = null) => new ParameterLaporan($this->k->tahun->id, $jenis, $nilai);
});

function barisUnit($hasil, string $kode): array
{
    return $hasil->firstWhere('unit_kode', $kode);
}

test('bulanan Maret, semester 1, dan tahunan sesuai perhitungan manual', function () {
    $maret = barisUnit(LaporanRealisasiQuery::untuk(($this->param)('bulanan', 3), $this->pimpinan), 'PMAT');
    expect($maret)->toMatchArray([
        'pagu' => '10000000.00', 'komitmen' => '1000000.00', 'disetujui' => '1000000.00', 'dicairkan' => '1000000.00',
        'realisasi' => '400000.00', 'realisasi_kumulatif' => '400000.00', 'sisa_pagu' => '9000000.00', 'serapan_persen' => '4.00', 'sumber' => 'langsung',
    ]);

    $semester1 = LaporanRealisasiQuery::untuk(($this->param)('semester', 1), $this->pimpinan);
    expect(barisUnit($semester1, 'PMAT'))->toMatchArray(['dicairkan' => '1000000.00', 'realisasi' => '700000.00', 'serapan_persen' => '7.00'])
        ->and(barisUnit($semester1, 'PBIO'))->toMatchArray(['dicairkan' => '0.00', 'realisasi' => '0.00', 'disetujui' => '0.00']);

    $tahunan = LaporanRealisasiQuery::untuk(($this->param)('tahunan'), $this->pimpinan);
    expect(barisUnit($tahunan, 'PBIO'))->toMatchArray(['pagu' => '5000000.00', 'dicairkan' => '500000.00', 'realisasi' => '200000.00', 'serapan_persen' => '4.00'])
        ->and(LaporanRealisasiQuery::total($tahunan))->toMatchArray(['pagu' => '15000000.00', 'realisasi' => '900000.00', 'serapan_persen' => '6.00']);
});

test('rentang terkunci memakai snapshot walau data mentah berubah', function () {
    foreach (Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', '<=', 3)->orderBy('bulan')->get() as $periode) {
        app(KunciPeriode::class)->handle($periode, $this->k->keu, abaikanPeringatan: true);
    }

    DB::table('realisasi_item')->where('jumlah_bruto', '400000.00')->update(['jumlah_bruto' => '999.00']);
    VersiCacheDasbor::naikkan();

    $maret = barisUnit(LaporanRealisasiQuery::untuk(($this->param)('bulanan', 3), $this->pimpinan), 'PMAT');
    expect($maret)->toMatchArray(['realisasi' => '400000.00', 'dicairkan' => '1000000.00', 'disetujui' => '1000000.00', 'sumber' => 'snapshot']);

    $april = barisUnit(LaporanRealisasiQuery::untuk(($this->param)('bulanan', 4), $this->pimpinan), 'PMAT');
    expect($april['sumber'])->toBe('langsung')->and($april['realisasi_kumulatif'])->toBe('300999.00');
});

test('cache dipakai pada panggilan kedua dan berganti setelah naikkan()', function () {
    $param = ($this->param)('tahunan');
    LaporanRealisasiQuery::untuk($param, $this->pimpinan);

    DB::enableQueryLog();
    DB::flushQueryLog();
    LaporanRealisasiQuery::untuk($param, $this->pimpinan);
    expect(DB::getQueryLog())->toHaveCount(0);

    VersiCacheDasbor::naikkan();
    LaporanRealisasiQuery::untuk($param, $this->pimpinan);
    expect(count(DB::getQueryLog()))->toBeGreaterThan(0);
});

test('admin-unit hanya mendapat unitnya walau meminta unit lain', function () {
    $param = new ParameterLaporan($this->k->tahun->id, 'tahunan', null, $this->k->pbio->id);

    $hasil = LaporanRealisasiQuery::untuk($param, $this->k->adminPmat);

    expect($hasil->pluck('unit_kode')->all())->toBe(['PMAT']);
});

test('SPJ terlambat hanya unit sendiri untuk admin-unit', function () {
    $this->travelTo('2027-08-20 10:00:00');

    expect(SpjTerlambatQuery::untuk($this->pimpinan)->pluck('unit')->sort()->values()->all())->toBe(['PBIO', 'PMAT'])
        ->and(SpjTerlambatQuery::untuk($this->k->adminPmat)->pluck('unit')->all())->toBe(['PMAT'])
        ->and(SpjTerlambatQuery::untuk($this->k->adminPmat)->first()['hari_terlambat'])->toBeGreaterThan(0);
});
