<?php

use App\Actions\Pencairan\BatalkanPencairan;
use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Periode\KunciPeriode;
use App\Actions\Spj\CatatRealisasi;
use App\Enums\StatusPeriode;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PeriodeTerkunciException;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Models\JenisPencairan;
use App\Models\Pencairan;
use App\Models\Periode;
use App\Models\RekapPeriode;
use App\Models\RiwayatStatus;
use App\Support\VersiCacheDasbor;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->travelTo('2027-01-25 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->p = buatPengajuanDisetujui($this->k, kegiatan: ['tanggal_mulai_kegiatan' => '2027-01-10', 'tanggal_selesai_kegiatan' => '2027-01-11']);
    [$cair, $bukti] = dataPencairan('300000', '2027-01-20');
    $this->pencairan = app(CatatPencairan::class)->handle($this->p, $cair, $bukti, $this->k->bpp);
    [$data, $tautan] = dataRealisasi($this->p->fresh(), '1', '250000', tanggal: '2027-01-15');
    app(CatatRealisasi::class)->handle($this->p->fresh(), $data, $tautan, $this->k->adminPmat);

    $this->travelTo('2027-02-03 09:00:00');
    $this->januari = Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 1)->firstOrFail();
    $this->februari = Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 2)->firstOrFail();
});

test('mengunci Februari sebelum Januari → ditolak', function () {
    expect(fn () => app(KunciPeriode::class)->handle($this->februari, $this->k->keu, true))->toThrow(KesalahanDomain::class, 'Januari 2027');
});

test('kunci Januari menghasilkan snapshot per unit dengan angka benar', function () {
    $versi = VersiCacheDasbor::versi();
    $periode = app(KunciPeriode::class)->handle($this->januari, $this->k->keu, abaikanPeringatan: true);

    expect($periode->status)->toBe(StatusPeriode::Terkunci)
        ->and($periode->versi_kunci)->toBe(1)
        ->and($periode->dikunci_oleh)->toBe($this->k->keu->id)
        ->and(VersiCacheDasbor::versi())->toBeGreaterThan($versi)
        ->and(RiwayatStatus::where('model_type', 'periode')->where('model_id', $periode->id)->value('ke'))->toBe('terkunci');

    $rekap = RekapPeriode::where('periode_id', $periode->id)->where('unit_id', $this->k->pmat->id)->firstOrFail();
    expect($rekap->pagu)->toBe('100000000.00')
        ->and($rekap->total_disetujui_kumulatif)->toBe('300000.00')
        ->and($rekap->total_pencairan)->toBe('300000.00')
        ->and($rekap->total_realisasi)->toBe('250000.00')
        ->and($rekap->total_pajak)->toBe('0.00')
        ->and($rekap->total_pengembalian)->toBe('0.00')
        ->and($rekap->perAkun()->where('akun_id', $this->k->akun->id)->value('total_realisasi'))->toBe('250000.00');
});

test('setelah dikunci: realisasi, pencairan, dan pembatalan pencairan bertanggal Januari ditolak', function () {
    app(KunciPeriode::class)->handle($this->januari, $this->k->keu, true);

    [$data, $tautan] = dataRealisasi($this->p->fresh(), '1', '10000', tanggal: '2027-01-16');
    expect(fn () => app(CatatRealisasi::class)->handle($this->p->fresh(), $data, $tautan, $this->k->adminPmat))->toThrow(PeriodeTerkunciException::class);

    $lain = buatPengajuanDisetujui($this->k, kegiatan: ['tanggal_mulai_kegiatan' => '2027-01-10', 'tanggal_selesai_kegiatan' => '2027-01-11']);
    [$cair, $bukti] = dataPencairan('1000', '2027-01-21');
    expect(fn () => app(CatatPencairan::class)->handle($lain, $cair, $bukti, $this->k->bpp))->toThrow(PeriodeTerkunciException::class);

    expect(fn () => app(BatalkanPencairan::class)->handle($this->pencairan->fresh(), 'Koreksi nominal transfer', $this->k->bpp))->toThrow(PeriodeTerkunciException::class);
    expect(Pencairan::where('status', 'dicatat')->count())->toBe(1);
});

test('pra-cek memunculkan peringatan untuk SPJ belum diterima', function () {
    $peringatan = KunciPeriode::peringatan($this->januari);

    expect($peringatan)->toHaveCount(2)
        ->and(fn () => app(KunciPeriode::class)->handle($this->januari, $this->k->keu))->toThrow(KesalahanDomain::class, 'SPJ-nya belum diterima');
    expect($this->januari->fresh()->status)->toBe(StatusPeriode::Terbuka);
});

test('admin-unit dan wadek tidak bisa mengunci (403)', function () {
    expect(fn () => app(KunciPeriode::class)->handle($this->januari, $this->k->adminPmat, true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(KunciPeriode::class)->handle($this->januari, $this->k->wadek, true))->toThrow(AuthorizationException::class);
});

test('panel: pencairan bertanggal periode terkunci menampilkan notifikasi yang jelas', function () {
    app(KunciPeriode::class)->handle($this->januari, $this->k->keu, true);
    $lain = buatPengajuanDisetujui($this->k, kegiatan: ['tanggal_mulai_kegiatan' => '2027-01-10', 'tanggal_selesai_kegiatan' => '2027-01-11']);

    Livewire\Livewire::actingAs($this->k->bpp)->test(ViewPengajuan::class, ['record' => $lain->getRouteKey()])
        ->callAction('catatPencairan', data: [
            'jenis_pencairan_id' => JenisPencairan::firstOrCreate(['kode' => 'LS'], ['nama' => 'Pembayaran Langsung'])->id,
            'tanggal' => '2027-01-21',
            'jumlah' => '1000',
            'nomor_bukti' => 'TRF-9',
            'tautan_bukti' => [['jenis' => 'bukti_pencairan', 'label' => 'Bukti', 'url' => 'https://drive.google.com/file/d/1Tk/view', 'akses_terbatas_dikonfirmasi' => true]],
        ])
        ->assertNotified('Gagal mencatat pencairan');

    expect(Pencairan::where('pengajuan_id', $lain->id)->count())->toBe(0);
});
