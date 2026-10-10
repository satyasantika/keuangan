<?php

use App\Actions\Berkas\SimpanTautanBerkas;
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
use App\Enums\StatusPengajuan;
use App\Enums\StatusSpj;
use App\Models\Akun;
use App\Models\JenisPajak;
use App\Models\RiwayatStatus;
use App\Models\TautanBerkas;
use App\Models\TautanBerkasBeku;
use Database\Seeders\JenisPajakSeeder;

test('alur lengkap: pagu → pengajuan → persetujuan → pencairan → realisasi + pajak → SPJ → pengembalian → selesai', function () {
    $this->travelTo('2027-06-20 10:00:00');
    $k = siapkanAnggaran('100000000.00');
    $this->seed(JenisPajakSeeder::class);
    $akunHonor = Akun::factory()->create();

    // Draf dengan dua item: 20 × 50.000 = 1.000.000 dan 2 × 1.500.000 = 3.000.000 → total 4.000.000.
    $p = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($k->tahun->id, ['tanggal_mulai_kegiatan' => '2027-06-10', 'tanggal_selesai_kegiatan' => '2027-06-11']), [
        itemRab($k->akun->id, '20', '50000', ['uraian' => 'Konsumsi']),
        itemRab($akunHonor->id, '2', '1500000', ['uraian' => 'Honor narasumber', 'satuan' => 'OJ']),
    ], $k->adminPmat);
    app(SimpanTautanBerkas::class)->handle($p, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorAlur/view', 'TOR', false, $k->adminPmat);

    $p = app(AjukanPengajuan::class)->handle($p->fresh(), $k->adminPmat);
    $p = app(VerifikasiPengajuan::class)->handle($p, $k->kepalaPmat);
    $p = app(VerifikasiPengajuan::class)->handle($p, $k->keu);
    $p = app(SetujuiPengajuan::class)->handle($p, $k->wadek)->fresh();
    expect($p->status)->toBe(StatusPengajuan::Disetujui)->and($p->total_disetujui)->toBe('4000000.00');

    [$cair, $bukti] = dataPencairan('4000000', '2027-06-12');
    app(CatatPencairan::class)->handle($p, $cair, $bukti, $k->bpp);
    $p->refresh();
    expect($p->status)->toBe(StatusPengajuan::Dicairkan)->and($p->batas_spj->toDateString())->toBe('2027-06-26');

    [$konsumsi, $honor] = $p->rabAktif->item()->get()->all();
    $realisasi1 = app(CatatRealisasi::class)->handle($p, [
        'rab_item_id' => $konsumsi->id, 'tanggal_bukti' => '2027-06-10', 'nomor_bukti' => 'KW-1', 'uraian' => 'Konsumsi 19 orang', 'volume' => '19', 'harga_satuan' => '50000',
    ], [
        ['jenis' => 'kuitansi', 'label' => 'Kuitansi konsumsi', 'url' => 'https://drive.google.com/file/d/1KwKonsumsi/view', 'akses_terbatas_dikonfirmasi' => true],
        ['jenis' => 'daftar_hadir', 'label' => 'Daftar hadir', 'url' => 'https://drive.google.com/file/d/1Hadir/view', 'akses_terbatas_dikonfirmasi' => false],
    ], $k->adminPmat);
    $realisasi2 = app(CatatRealisasi::class)->handle($p, [
        'rab_item_id' => $honor->id, 'tanggal_bukti' => '2027-06-11', 'nomor_bukti' => 'KW-2', 'uraian' => 'Honor 2 jam', 'volume' => '2', 'harga_satuan' => '1500000',
    ], [
        ['jenis' => 'kuitansi', 'label' => 'Kuitansi honor', 'url' => 'https://drive.google.com/file/d/1KwHonor/view', 'akses_terbatas_dikonfirmasi' => true],
    ], $k->adminPmat);

    app(CatatPotonganPajak::class)->handle($realisasi2->fresh(), [
        'jenis_pajak_id' => JenisPajak::where('kode', 'pph21')->value('id'), 'dasar_pengenaan' => '3000000', 'nilai' => '150000',
    ], $k->adminPmat);

    $spj = app(AjukanSpj::class)->handle($realisasi1->spj, $k->adminPmat, true);
    $spj = app(TerimaSpj::class)->handle($spj, $k->bpp, 'Lengkap');
    expect($spj->status)->toBe(StatusSpj::Diterima)
        ->and(TautanBerkasBeku::where('spj_id', $spj->id)->count())->toBe(TautanBerkas::count()); // 3 realisasi + 1 pencairan + 1 TOR

    $p->refresh();
    expect($p->total_realisasi)->toBe('3950000.00')
        ->and($p->total_pajak)->toBe('150000.00')
        ->and($p->sisaDana())->toBe('50000.00')
        ->and($p->status)->toBe(StatusPengajuan::Dicairkan);

    $kembali = app(CatatPengembalian::class)->handle($p, ['tanggal_setor' => '2027-06-19', 'jumlah' => '50000', 'nomor_bukti_setor' => 'NTPN-9'], [
        ['label' => 'Bukti setor', 'url' => 'https://drive.google.com/file/d/1SetorAlur/view', 'akses_terbatas_dikonfirmasi' => true],
    ], $k->adminPmat);
    app(VerifikasiPengembalian::class)->handle($kembali, $k->bpp);

    $p->refresh();
    expect($p->status)->toBe(StatusPengajuan::Selesai)
        ->and($p->total_dicairkan)->toBe('4000000.00')
        ->and($p->total_dikembalikan)->toBe('50000.00')
        ->and($p->selesai_pada)->not->toBeNull()
        ->and(TautanBerkasBeku::where('pemilik_type', 'pengembalian')->count())->toBe(1)
        ->and(RiwayatStatus::count())->toBeGreaterThanOrEqual(10)
        ->and(RiwayatStatus::where('model_type', 'pengajuan')->where('model_id', $p->id)->pluck('ke')->all())
        ->toBe(['diajukan', 'diverifikasi_unit', 'diverifikasi_keuangan', 'disetujui', 'dicairkan', 'selesai']);

    $this->artisan('keuangan:rekonsiliasi')->expectsOutput('Tidak ada selisih total.')->assertSuccessful();
});
