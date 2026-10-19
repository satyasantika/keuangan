<?php

use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengajuan\AjukanPengajuan;
use App\Actions\Pengembalian\CatatPengembalian;
use App\Actions\Pengembalian\VerifikasiPengembalian;
use App\Actions\Periode\BukaKunciPeriode;
use App\Actions\Periode\KunciPeriode;
use App\Actions\Persetujuan\KembalikanPengajuan;
use App\Actions\Persetujuan\TolakPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Actions\Revisi\AjukanRevisiRab;
use App\Actions\Revisi\BuatRevisiRab;
use App\Actions\Revisi\TolakRevisiRab;
use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\TerimaSpj;
use App\Enums\Peran;
use App\Models\Periode;
use App\Notifications\IndikasiBuktiGanda;
use App\Notifications\PencairanDicatat;
use App\Notifications\PengajuanDikembalikan;
use App\Notifications\PengajuanDisetujui;
use App\Notifications\PengajuanDitolak;
use App\Notifications\PengajuanMenungguTindakan;
use App\Notifications\PengajuanSelesai;
use App\Notifications\PengembalianMenungguVerifikasi;
use App\Notifications\PeriodeDibukaKembali;
use App\Notifications\PeriodeDikunci;
use App\Notifications\RevisiRabDiputuskan;
use App\Notifications\RevisiRabMenungguTindakan;
use App\Notifications\SpjDiputuskan;
use App\Notifications\SpjMenungguVerifikasi;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    Notification::fake();
});

test('pengajuan diajukan → kepala-unit unit terkait; diverifikasi unit → admin-keuangan; → wadek', function () {
    $kepalaPbio = penggunaDenganPeran(Peran::KepalaUnit, ['unit_id' => $this->k->pbio->id]);
    $p = buatPengajuanDiajukan($this->k);

    Notification::assertSentTo($this->k->kepalaPmat, PengajuanMenungguTindakan::class);
    Notification::assertNotSentTo([$kepalaPbio, $this->k->keu], PengajuanMenungguTindakan::class);

    $p = app(VerifikasiPengajuan::class)->handle($p, $this->k->kepalaPmat);
    Notification::assertSentTo($this->k->keu, PengajuanMenungguTindakan::class);

    app(VerifikasiPengajuan::class)->handle($p, $this->k->keu);
    Notification::assertSentTo($this->k->wadek, PengajuanMenungguTindakan::class, fn ($n) => $n->tahap->value === 'wadek');
});

test('pembuat yang juga admin-keuangan tidak menerima notifikasi untuk pengajuannya sendiri; pengguna nonaktif dilewati', function () {
    $ganda = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pmat->id]);
    $ganda->assignRole(Peran::AdminKeuangan->value);
    $nonaktif = penggunaDenganPeran(Peran::AdminKeuangan, ['aktif' => false]);

    $p = app(AjukanPengajuan::class)->handle(buatPengajuanDraf($this->k, oleh: $ganda), $ganda);
    app(VerifikasiPengajuan::class)->handle($p, $this->k->kepalaPmat);

    Notification::assertSentTo($this->k->keu, PengajuanMenungguTindakan::class);
    Notification::assertNotSentTo([$ganda, $nonaktif], PengajuanMenungguTindakan::class);
});

test('dikembalikan dan ditolak → pembuat + admin-unit; disetujui → admin-unit, kepala-unit, bpp', function () {
    $a = buatPengajuanDiajukan($this->k);
    app(KembalikanPengajuan::class)->handle($a, $this->k->kepalaPmat, 'Lengkapi rincian volume');
    Notification::assertSentTo($this->k->adminPmat, PengajuanDikembalikan::class, fn ($n) => $n->catatan === 'Lengkapi rincian volume');

    $b = buatPengajuanDiajukan($this->k);
    app(TolakPengajuan::class)->handle($b, $this->k->kepalaPmat, 'Tidak sesuai rencana unit');
    Notification::assertSentTo($this->k->adminPmat, PengajuanDitolak::class);

    buatPengajuanDisetujui($this->k);
    Notification::assertSentTo([$this->k->adminPmat, $this->k->kepalaPmat, $this->k->bpp], PengajuanDisetujui::class);
});

test('pencairan → unit; SPJ diajukan → keuangan & bpp; diterima → unit; pengembalian → bpp; selesai → unit', function () {
    $p = buatPengajuanDisetujui($this->k);
    [$cair, $bukti] = dataPencairan('300000');
    app(CatatPencairan::class)->handle($p, $cair, $bukti, $this->k->bpp);
    Notification::assertSentTo([$this->k->adminPmat, $this->k->kepalaPmat], PencairanDicatat::class);

    [$data, $tautan] = dataRealisasi($p->fresh(), '1', '250000');
    $spj = app(CatatRealisasi::class)->handle($p->fresh(), $data, $tautan, $this->k->adminPmat)->spj;
    $spj = app(AjukanSpj::class)->handle($spj, $this->k->adminPmat, true);
    Notification::assertSentTo([$this->k->keu, $this->k->bpp], SpjMenungguVerifikasi::class);

    app(TerimaSpj::class)->handle($spj, $this->k->bpp);
    Notification::assertSentTo($this->k->adminPmat, SpjDiputuskan::class);

    $k = app(CatatPengembalian::class)->handle($p->fresh(), ['tanggal_setor' => '2027-06-19', 'jumlah' => '50000', 'nomor_bukti_setor' => 'NTPN'], [
        ['label' => 'Bukti setor', 'url' => 'https://drive.google.com/file/d/1SetorN/view', 'akses_terbatas_dikonfirmasi' => true],
    ], $this->k->adminPmat);
    Notification::assertSentTo($this->k->bpp, PengembalianMenungguVerifikasi::class);

    app(VerifikasiPengembalian::class)->handle($k, $this->k->bpp);
    Notification::assertSentTo($this->k->adminPmat, PengajuanSelesai::class);
});

test('revisi: diajukan → admin-keuangan; ditolak → penyusun', function () {
    $p = buatPengajuanDicairkan($this->k);
    $revisi = app(AjukanRevisiRab::class)->handle(app(BuatRevisiRab::class)->handle($p, 'Penyesuaian jumlah peserta', $this->k->adminPmat), $this->k->adminPmat);
    Notification::assertSentTo($this->k->keu, RevisiRabMenungguTindakan::class);

    app(TolakRevisiRab::class)->handle($revisi, $this->k->keu, 'Kenaikan tidak beralasan');
    Notification::assertSentTo($this->k->adminPmat, RevisiRabDiputuskan::class);
});

test('periode dikunci/dibuka → peran keuangan', function () {
    $jan = Periode::where('tahun_anggaran_id', $this->k->tahun->id)->where('bulan', 1)->firstOrFail();
    app(KunciPeriode::class)->handle($jan, $this->k->keu, true);
    Notification::assertSentTo([$this->k->keu, $this->k->bpp, $this->k->wadek], PeriodeDikunci::class);
    Notification::assertNotSentTo($this->k->adminPmat, PeriodeDikunci::class);

    app(BukaKunciPeriode::class)->handle($jan->fresh(), 'Koreksi resmi dari bagian keuangan universitas', $this->k->wadek);
    Notification::assertSentTo($this->k->keu, PeriodeDibukaKembali::class);
});

test('indikasi bukti ganda → admin-keuangan & bpp', function () {
    $a = buatPengajuanDicairkan($this->k);
    $b = buatPengajuanDicairkan($this->k);
    [$d1, $t1] = dataRealisasi($a, '1', '100000', '1SamaN');
    app(CatatRealisasi::class)->handle($a, $d1, $t1, $this->k->adminPmat);
    [$d2, $t2] = dataRealisasi($b, '1', '100000', '1SamaN');
    app(CatatRealisasi::class)->handle($b, $d2, $t2, $this->k->adminPmat);

    Notification::assertSentTo([$this->k->keu, $this->k->bpp], IndikasiBuktiGanda::class);
});

test('notifikasi diantrekan ke "default"; surel berawalan [Keuangan FKIP] tanpa nama penerima realisasi atau URL Drive', function () {
    $p = buatPengajuanDisetujui($this->k);
    [$cair, $bukti] = dataPencairan('300000');
    app(CatatPencairan::class)->handle($p, $cair, $bukti, $this->k->bpp);

    Notification::assertSentTo($this->k->adminPmat, PencairanDicatat::class, function (PencairanDicatat $n) {
        $surel = $n->toMail($this->k->adminPmat);
        $teks = $surel->subject.' '.implode(' ', $surel->introLines).' '.$surel->actionUrl;

        return $n->viaQueues()['mail'] === 'default'
            && $n->queue === 'default'
            && str_starts_with($surel->subject, '[Keuangan FKIP]')
            && ! str_contains($teks, 'Penyedia')
            && ! str_contains($teks, 'drive.google.com');
    });
});
