<?php

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Master\TetapkanPagu;
use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Actions\Pengembalian\CatatPengembalian;
use App\Actions\Pengembalian\VerifikasiPengembalian;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatPotonganPajak;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\TerimaSpj;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Laporan\KatalogLaporan;
use App\Models\JenisPajak;
use App\Models\KategoriPenggunaanDana;
use App\Models\Pagu;
use App\Models\User;
use Database\Seeders\JenisPajakSeeder;
use Database\Seeders\KategoriPenggunaanDanaContohSeeder;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('10000000.00');
    $this->seed([KategoriPenggunaanDanaContohSeeder::class, JenisPajakSeeder::class]);
    $this->kategori = KategoriPenggunaanDana::where('kode', 'penelitian')->firstOrFail();

    // PMAT: dicairkan 300 rb, realisasi 250 rb + pajak 5 rb, SPJ diterima, pengembalian 50 rb → selesai.
    $this->p = buatPengajuanDisetujui($this->k, kegiatan: ['kategori_penggunaan_dana_id' => $this->kategori->id]);
    [$cair, $bukti] = dataPencairan('300000');
    app(CatatPencairan::class)->handle($this->p, $cair, $bukti, $this->k->bpp);
    [$d, $t] = dataRealisasi($this->p->fresh(), '1', '250000');
    $r = app(CatatRealisasi::class)->handle($this->p->fresh(), $d, $t, $this->k->adminPmat);
    app(CatatPotonganPajak::class)->handle($r->fresh(), ['jenis_pajak_id' => JenisPajak::where('kode', 'pph23')->value('id'), 'dasar_pengenaan' => '250000', 'nilai' => '5000'], $this->k->adminPmat);
    app(TerimaSpj::class)->handle(app(AjukanSpj::class)->handle($r->spj, $this->k->adminPmat, true), $this->k->bpp);
    $kembali = app(CatatPengembalian::class)->handle($this->p->fresh(), ['tanggal_setor' => '2027-06-19', 'jumlah' => '50000', 'nomor_bukti_setor' => 'NTPN'], [
        ['label' => 'Bukti setor', 'url' => 'https://drive.google.com/file/d/1SetorLk/view', 'akses_terbatas_dikonfirmasi' => true],
    ], $this->k->adminPmat);
    app(VerifikasiPengembalian::class)->handle($kembali, $this->k->bpp);

    // PMAT kedua: dicairkan tanpa SPJ (akan terlambat).
    $this->terlambat = buatPengajuanDicairkan($this->k);

    // PBIO (dijadikan non-prodi) dengan kategori sama → tidak boleh muncul di LAP-08.
    $paguPbio = Pagu::factory()->create(['tahun_anggaran_id' => $this->k->tahun->id, 'unit_id' => $this->k->pbio->id, 'jumlah' => '5000000.00', 'dibuat_oleh' => $this->k->keu->id]);
    app(TetapkanPagu::class)->handle($paguPbio, $this->k->wadek);
    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    $kepalaPbio = penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $this->k->pbio->id]);
    $b = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($this->k->tahun->id, ['kategori_penggunaan_dana_id' => $this->kategori->id]), [itemRab($this->k->akun->id, '1', '100000')], $adminPbio);
    app(SimpanTautanBerkas::class)->handle($b, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorB/view', 'TOR', false, $adminPbio);
    $b = app(AjukanPengajuan::class)->handle($b->fresh(), $adminPbio);
    $b = app(VerifikasiPengajuan::class)->handle(app(VerifikasiPengajuan::class)->handle($b, $kepalaPbio), $this->k->keu);
    $b = app(SetujuiPengajuan::class)->handle($b, $this->k->wadek)->fresh();
    [$c2, $b2] = dataPencairan('100000');
    app(CatatPencairan::class)->handle($b, $c2, $b2, $this->k->bpp);
    [$d2, $t2] = dataRealisasi($b->fresh(), '1', '100000');
    app(CatatRealisasi::class)->handle($b->fresh(), $d2, $t2, $adminPbio);
    $this->k->pbio->update(['jenis' => 'laboratorium']);

    $this->travelTo('2027-07-20 10:00:00');
    $this->param = ['tahun_anggaran_id' => $this->k->tahun->id];
});

test('LAP-03 kartu kegiatan PDF untuk pemilik; unit lain 403; ringkasan item benar', function () {
    $respons = $this->actingAs($this->k->adminPmat)->get(route('pengajuan.kartu', $this->p));
    expect($respons->headers->get('Content-Type'))->toBe('application/pdf');

    $adminPbio = User::where('unit_id', $this->k->pbio->id)->firstOrFail();
    $this->actingAs($adminPbio)->get(route('pengajuan.kartu', $this->p))->assertForbidden();

    $data = KatalogLaporan::bangun('LAP-03', [...$this->param, 'pengajuan_id' => $this->p->id], $this->k->adminPmat);
    expect($data->baris[0])->toBe(['Konsumsi peserta', '300000.00', '250000.00', '50000.00']);

    $this->actingAs($this->k->adminPmat)->get(route('laporan.ekspor', ['jenis' => 'LAP-03', 'format' => 'pdf', ...$this->param, 'pengajuan_id' => $this->p->id]))
        ->assertRedirect(route('pengajuan.kartu', $this->p));
});

test('LAP-04 SPJ terlambat: kepala-unit hanya unitnya', function () {
    $data = KatalogLaporan::bangun('LAP-04', $this->param, $this->k->keu);
    $nomor = collect($data->baris)->pluck(1)->all();

    expect($nomor)->toContain($this->terlambat->nomor)
        ->and($nomor)->not->toContain($this->p->nomor)
        ->and(collect(KatalogLaporan::bangun('LAP-04', $this->param, $this->k->kepalaPmat)->baris)->pluck(0)->unique()->all())->toBe(['PMAT']);
});

test('LAP-05 rekap pajak dan LAP-06 rekap pengembalian', function () {
    $pajak = KatalogLaporan::bangun('LAP-05', $this->param, $this->k->bpp);
    expect($pajak->baris)->toBe([[6, 'PPh Pasal 23', 'PMAT', '5000.00', 1, 0, 1]])
        ->and($pajak->judul)->toContain('perlu verifikasi');

    $kembali = KatalogLaporan::bangun('LAP-06', $this->param, $this->k->keu);
    expect($kembali->baris)->toBe([[6, 'PMAT', '50000.00', 1]]);
});

test('LAP-08 hanya memuat prodi', function () {
    $data = KatalogLaporan::bangun('LAP-08', $this->param, $this->k->keu);

    expect(collect($data->baris)->pluck(0)->unique()->values()->all())->toBe(['PMAT'])
        ->and($data->baris[0][1])->toBe('Penelitian')
        ->and($data->baris[0][4])->toBe('250000.00')
        ->and($data->judul)->toContain('perlu verifikasi');
});

test('peran tak berhak tidak melihat opsi laporan dan permintaan langsung ditolak', function () {
    $opsi = KatalogLaporan::untuk($this->k->adminPmat);

    expect($opsi)->not->toHaveKey('LAP-05')->not->toHaveKey('LAP-06')->not->toHaveKey('LAP-08')->toHaveKey('LAP-01')->toHaveKey('LAP-03');

    $this->actingAs($this->k->adminPmat)->get(route('laporan.ekspor', ['jenis' => 'LAP-05', 'format' => 'xlsx', ...$this->param]))->assertForbidden();
    $this->actingAs(penggunaDenganPeran(Peran::Pimpinan))->get(route('laporan.ekspor', ['jenis' => 'LAP-05', 'format' => 'xlsx', ...$this->param]))->assertForbidden();

    $this->actingAs($this->k->bpp)->get(route('laporan.ekspor', ['jenis' => 'LAP-05', 'format' => 'xlsx', ...$this->param]))->assertOk();
});
