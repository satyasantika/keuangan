<?php

use App\Actions\Revisi\AjukanRevisiRab;
use App\Actions\Revisi\BuatRevisiRab;
use App\Actions\Revisi\SetujuiRevisiRab;
use App\Actions\Revisi\SimpanItemRevisi;
use App\Actions\Revisi\TolakRevisiRab;
use App\Actions\Revisi\VerifikasiRevisiRab;
use App\Actions\Spj\CatatRealisasi;
use App\Enums\StatusRab;
use App\Exceptions\AksiSedangDiprosesException;
use App\Exceptions\PelanggaranPemisahanTugasException;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\RiwayatStatus;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('10000000.00');
});

/**
 * Pengajuan dicairkan dengan realisasi 200.000 dan revisi diajukan yang menaikkan item ke 3 × 150.000.
 */
function revisiDiajukan(object $k): array
{
    $p = buatPengajuanDicairkan($k);
    [$data, $tautan] = dataRealisasi($p, '1', '200000');
    $realisasi = app(CatatRealisasi::class)->handle($p, $data, $tautan, $k->adminPmat);

    $revisi = app(BuatRevisiRab::class)->handle($p->fresh(), 'Tambah peserta kegiatan', $k->adminPmat);
    $item = $revisi->item()->first();
    app(SimpanItemRevisi::class)->handle($revisi, [[
        'id' => $item->id, 'akun_id' => $item->akun_id, 'uraian' => $item->uraian, 'volume' => '3', 'satuan' => $item->satuan, 'harga_satuan' => '150000',
    ]], $k->adminPmat);

    return [$p, app(AjukanRevisiRab::class)->handle($revisi->fresh(), $k->adminPmat), $realisasi];
}

test('alur 2 tahap → versi baru aktif, versi lama digantikan, total_disetujui berubah, realisasi dipetakan', function () {
    [$p, $revisi, $realisasi] = revisiDiajukan($this->k);
    $itemLama = $p->rabAktif->item()->first();

    $revisi = app(VerifikasiRevisiRab::class)->handle($revisi, $this->k->keu);
    expect($revisi->status)->toBe(StatusRab::DiverifikasiKeuangan);

    $revisi = app(SetujuiRevisiRab::class)->handle($revisi, $this->k->wadek);
    $p->refresh();
    $itemBaru = RabItem::where('rab_id', $revisi->id)->first();

    expect($revisi->fresh()->status)->toBe(StatusRab::Disetujui)
        ->and($revisi->fresh()->disetujui_oleh)->toBe($this->k->wadek->id)
        ->and($itemLama->rab->fresh()->status)->toBe(StatusRab::Digantikan)
        ->and($p->rab_aktif_id)->toBe($revisi->id)
        ->and($p->total_disetujui)->toBe('450000.00')
        ->and($realisasi->fresh()->rab_item_id)->toBe($itemBaru->id)
        ->and($itemBaru->asal_rab_item_id)->toBe($itemLama->id)
        ->and(RealisasiItem::where('rab_item_id', $itemBaru->id)->sum('jumlah_bruto'))->toEqual(200000)
        ->and(RealisasiItem::sedangMemetakanRevisi())->toBeFalse()
        ->and(RiwayatStatus::where('model_type', 'pengajuan')->where('model_id', $p->id)->latest('id')->value('catatan'))->toBe('Revisi RAB ke-1 berlaku');

    // Sisa per item kini dihitung terhadap item versi baru.
    [$lagi, $tautan] = dataRealisasi($p, '1', '250000');
    $baru = app(CatatRealisasi::class)->handle($p, $lagi, $tautan, $this->k->adminPmat);
    expect($baru->rab_item_id)->toBe($itemBaru->id)->and($p->fresh()->total_realisasi)->toBe('450000.00');
});

test('jalur tanpa wadek: verifikasi keuangan langsung memberlakukan revisi', function () {
    [$p, $revisi] = revisiDiajukan($this->k);
    // Snapshot pengajuan menentukan jalur revisi (BR-06); simulasikan pengajuan yang diajukan tanpa tahap wadek.
    $p->forceFill(['wajib_wadek' => false])->save();

    app(VerifikasiRevisiRab::class)->handle($revisi, $this->k->keu);

    expect($revisi->fresh()->status)->toBe(StatusRab::Disetujui)
        ->and($p->fresh()->rab_aktif_id)->toBe($revisi->id);
});

test('pembuat revisi tidak bisa menyetujui', function () {
    [, $revisi] = revisiDiajukan($this->k);
    $this->k->adminPmat->assignRole('admin-keuangan');

    expect($this->k->adminPmat->can('verifikasi', $revisi))->toBeFalse()
        ->and(fn () => app(VerifikasiRevisiRab::class)->handle($revisi, $this->k->adminPmat))->toThrow(PelanggaranPemisahanTugasException::class);
});

test('revisi ditolak → RAB aktif tidak berubah dan revisi baru bernomor 2 dapat dibuat', function () {
    [$p, $revisi] = revisiDiajukan($this->k);
    $aktif = $p->rab_aktif_id;

    app(TolakRevisiRab::class)->handle($revisi, $this->k->keu, 'Kenaikan tidak beralasan');

    expect($revisi->fresh()->status)->toBe(StatusRab::Ditolak)
        ->and($p->fresh()->rab_aktif_id)->toBe($aktif);

    $kedua = app(BuatRevisiRab::class)->handle($p->fresh(), 'Revisi dengan alasan baru', $this->k->adminPmat);
    expect($kedua->nomor_revisi)->toBe(2);
});

test('dua klik setujui bersamaan → satu AksiSedangDiprosesException, satu berhasil', function () {
    [, $revisi] = revisiDiajukan($this->k);
    $revisi = app(VerifikasiRevisiRab::class)->handle($revisi, $this->k->keu);

    // Lock::block() memakai Carbon::now(); waktu harus berjalan agar batas tunggu lock tercapai.
    $this->travelBack();
    $lock = Cache::store(config('keuangan.lock_store'))->lock("lock:aksi:rab:{$revisi->id}", 15);
    $lock->get();
    expect(fn () => app(SetujuiRevisiRab::class)->handle($revisi, $this->k->wadek))->toThrow(AksiSedangDiprosesException::class);
    $lock->release();

    expect(app(SetujuiRevisiRab::class)->handle($revisi, $this->k->wadek)->status)->toBe(StatusRab::Disetujui);
});
