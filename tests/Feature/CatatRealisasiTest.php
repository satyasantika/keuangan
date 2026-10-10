<?php

use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\HapusRealisasi;
use App\Enums\Peran;
use App\Enums\StatusPeriode;
use App\Enums\StatusRab;
use App\Enums\StatusSpj;
use App\Events\IndikasiBuktiGandaTerdeteksi;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PeriodeTerkunciException;
use App\Exceptions\TransisiStatusTidakSahException;
use App\Models\Periode;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\TautanBerkas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->p = buatPengajuanDicairkan($this->k); // item 300.000
    $this->catat = app(CatatRealisasi::class);
});

test('realisasi pas sama dengan jumlah item → sukses, SPJ draf dibuat otomatis, total dihitung', function () {
    [$data, $tautan] = dataRealisasi($this->p, '2', '150000');

    $r = $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat);

    expect($r->jumlah_bruto)->toBe('300000.00')
        ->and($r->jumlah_neto)->toBe('300000.00')
        ->and($r->periode->bulan)->toBe(6)
        ->and($r->spj->status)->toBe(StatusSpj::Draf)
        ->and($r->spj->dibuat_oleh)->toBe($this->k->adminPmat->id)
        ->and($this->p->fresh()->total_realisasi)->toBe('300000.00')
        ->and($this->p->fresh()->sisaDana())->toBe('0.00');
});

test('melebihi 1 rupiah → ditolak dengan sisa item', function () {
    [$data, $tautan] = dataRealisasi($this->p, '1', '300000.01');

    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat))
        ->toThrow(KesalahanDomain::class, 'Sisa item Rp300.000,00');
});

test('dua realisasi parsial yang jumlahnya melebihi → yang kedua ditolak', function () {
    [$a, $ta] = dataRealisasi($this->p, '1', '200000');
    $this->catat->handle($this->p, $a, $ta, $this->k->adminPmat);

    [$b, $tb] = dataRealisasi($this->p, '1', '100000.01');
    expect(fn () => $this->catat->handle($this->p, $b, $tb, $this->k->adminPmat))->toThrow(KesalahanDomain::class, 'Rp100.000,00');
    expect(RealisasiItem::count())->toBe(1);
});

test('item dari RAB versi lama ditolak', function () {
    $rabLama = Rab::factory()->create(['pengajuan_id' => $this->p->id, 'nomor_revisi' => 9]);
    $lama = RabItem::factory()->create(['rab_id' => $rabLama->id]);
    $rabLama->forceFill(['status' => StatusRab::Digantikan])->save();
    [$data, $tautan] = dataRealisasi($this->p);
    $data['rab_item_id'] = $lama->id;

    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat))->toThrow(KesalahanDomain::class, 'RAB aktif');
});

test('hanya foto kegiatan (tanpa kuitansi/nota/faktur) → gagal', function () {
    [$data] = dataRealisasi($this->p);
    $tautan = [['jenis' => 'foto_kegiatan', 'label' => 'Foto', 'url' => 'https://drive.google.com/file/d/1Foto/view', 'akses_terbatas_dikonfirmasi' => false]];

    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat))->toThrow(ValidationException::class);
    expect(RealisasiItem::count())->toBe(0)->and(TautanBerkas::where('pemilik_type', 'realisasi_item')->count())->toBe(0);
});

test('kuitansi tanpa konfirmasi akses terbatas → gagal; bit.ly → gagal', function () {
    [$data, $tautan] = dataRealisasi($this->p);
    $tautan[0]['akses_terbatas_dikonfirmasi'] = false;
    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat))->toThrow(ValidationException::class);

    $tautan[0] = ['jenis' => 'kuitansi', 'label' => 'Kw', 'url' => 'https://bit.ly/abc', 'akses_terbatas_dikonfirmasi' => true];
    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat))->toThrow(ValidationException::class);
    expect(RealisasiItem::count())->toBe(0);
});

test('drive_file_id sama dengan realisasi pengajuan lain → indikasi bukti ganda + event; pengajuan sama tidak ditandai', function () {
    Event::fake([IndikasiBuktiGandaTerdeteksi::class]);
    $lain = buatPengajuanDicairkan($this->k);

    [$a, $ta] = dataRealisasi($this->p, '1', '100000', '1SamaPersis');
    $pertama = $this->catat->handle($this->p, $a, $ta, $this->k->adminPmat);

    [$b, $tb] = dataRealisasi($this->p, '1', '100000', '1SamaPersis');
    $keduaSama = $this->catat->handle($this->p, $b, $tb, $this->k->adminPmat);
    expect($pertama->fresh()->indikasi_bukti_ganda)->toBeFalse()
        ->and($keduaSama->fresh()->indikasi_bukti_ganda)->toBeFalse();
    Event::assertNotDispatched(IndikasiBuktiGandaTerdeteksi::class);

    [$c, $tc] = dataRealisasi($lain, '1', '100000', '1SamaPersis');
    $ganda = $this->catat->handle($lain, $c, $tc, $this->k->adminPmat);

    expect($ganda->fresh()->indikasi_bukti_ganda)->toBeTrue();
    Event::assertDispatched(IndikasiBuktiGandaTerdeteksi::class, fn ($e) => $e->realisasi->is($ganda) && count($e->realisasiLainIds) === 2);
});

test('tanggal di periode terkunci → gagal', function () {
    Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 6)->first()->forceFill(['status' => StatusPeriode::Terkunci])->save();
    [$data, $tautan] = dataRealisasi($this->p);

    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat))->toThrow(PeriodeTerkunciException::class);
});

test('pengajuan masih disetujui (belum dicairkan) → gagal', function () {
    $disetujui = buatPengajuanDisetujui($this->k);
    [$data, $tautan] = dataRealisasi($disetujui);

    expect(fn () => $this->catat->handle($disetujui, $data, $tautan, $this->k->adminPmat))->toThrow(TransisiStatusTidakSahException::class);
});

test('admin-unit unit lain → 403', function () {
    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
    [$data, $tautan] = dataRealisasi($this->p);

    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $adminPbio))->toThrow(AuthorizationException::class);
});

test('SPJ diajukan → realisasi tidak bisa ditambah', function () {
    [$data, $tautan] = dataRealisasi($this->p, '1', '100000');
    $r = $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat);
    $r->spj->forceFill(['status' => StatusSpj::Diajukan])->save();

    [$data2, $tautan2] = dataRealisasi($this->p, '1', '100000');
    expect(fn () => $this->catat->handle($this->p, $data2, $tautan2, $this->k->adminPmat))->toThrow(KesalahanDomain::class);
});

test('tanggal bukti terlalu jauh sebelum kegiatan ditolak', function () {
    [$data, $tautan] = dataRealisasi($this->p, tanggal: '2027-02-01'); // kegiatan mulai 10 Maret

    expect(fn () => $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat))->toThrow(ValidationException::class);
});

test('hapus realisasi: soft delete beserta tautan, total dihitung ulang', function () {
    [$data, $tautan] = dataRealisasi($this->p, '1', '100000');
    $r = $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat);

    app(HapusRealisasi::class)->handle($r, $this->k->adminPmat);

    expect(RealisasiItem::count())->toBe(0)
        ->and(RealisasiItem::withTrashed()->count())->toBe(1)
        ->and(TautanBerkas::where('pemilik_id', $r->id)->count())->toBe(0)
        ->and($this->p->fresh()->total_realisasi)->toBe('0.00');
});

test('mengubah realisasi yang ada tidak menghitung dirinya dua kali', function () {
    [$data, $tautan] = dataRealisasi($this->p, '2', '150000');
    $r = $this->catat->handle($this->p, $data, $tautan, $this->k->adminPmat);

    [$ubah] = dataRealisasi($this->p, '2', '140000');
    $r = $this->catat->handle($this->p, $ubah, [], $this->k->adminPmat, $r);

    expect($r->jumlah_bruto)->toBe('280000.00')
        ->and($this->p->fresh()->total_realisasi)->toBe('280000.00');
});
