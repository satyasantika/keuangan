<?php

use App\Actions\Revisi\AjukanRevisiRab;
use App\Actions\Revisi\BuatRevisiRab;
use App\Actions\Revisi\SimpanItemRevisi;
use App\Actions\Spj\CatatRealisasi;
use App\Enums\StatusRab;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PaguTidakCukupException;
use App\Models\RabItem;
use App\Models\RiwayatStatus;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('1000000.00');
    $this->p = buatPengajuanDicairkan($this->k); // RAB 300.000, dicairkan 300.000
});

function itemRevisi($rab): array
{
    return RabItem::query()->where('rab_id', $rab->id)->orderBy('urutan')->get()
        ->map(fn (RabItem $i) => ['id' => $i->id, 'akun_id' => $i->akun_id, 'uraian' => $i->uraian, 'volume' => (string) $i->volume, 'satuan' => $i->satuan, 'harga_satuan' => (string) $i->harga_satuan])
        ->all();
}

test('revisi pertama bernomor 1 dengan item tersalin beserta asal; RAB versi 0 tetap utuh', function () {
    $awal = $this->p->rabAktif;
    $revisi = app(BuatRevisiRab::class)->handle($this->p, 'Penyesuaian jumlah peserta', $this->k->adminPmat);

    $itemAwal = $awal->item()->first();
    $itemBaru = $revisi->item()->first();

    expect($revisi->nomor_revisi)->toBe(1)
        ->and($revisi->status)->toBe(StatusRab::Draf)
        ->and($revisi->total)->toBe('300000.00')
        ->and($itemBaru->asal_rab_item_id)->toBe($itemAwal->id)
        ->and($this->p->fresh()->rab_aktif_id)->toBe($awal->id)
        ->and($this->p->fresh()->total_diajukan)->toBe('300000.00')
        ->and($awal->fresh()->status)->toBe(StatusRab::Disetujui)
        ->and(RiwayatStatus::where('model_type', 'rab')->where('model_id', $revisi->id)->value('ke'))->toBe('draf');
});

test('revisi kedua tidak bisa dibuat saat revisi 1 masih draf', function () {
    app(BuatRevisiRab::class)->handle($this->p, 'Penyesuaian jumlah peserta', $this->k->adminPmat);

    expect(fn () => app(BuatRevisiRab::class)->handle($this->p->fresh(), 'Revisi lain sekaligus', $this->k->adminPmat))
        ->toThrow(AuthorizationException::class);
});

test('pengajuan diajukan (belum disetujui) tidak bisa direvisi', function () {
    $diajukan = buatPengajuanDiajukan($this->k);

    expect(fn () => app(BuatRevisiRab::class)->handle($diajukan, 'Ingin merevisi saja', $this->k->adminPmat))
        ->toThrow(AuthorizationException::class);
});

test('menurunkan item di bawah realisasi → ditolak saat diajukan', function () {
    [$data, $tautan] = dataRealisasi($this->p, '1', '200000');
    app(CatatRealisasi::class)->handle($this->p, $data, $tautan, $this->k->adminPmat);

    $revisi = app(BuatRevisiRab::class)->handle($this->p->fresh(), 'Kurangi konsumsi peserta', $this->k->adminPmat);
    $items = itemRevisi($revisi);
    $items[0]['harga_satuan'] = '90000'; // 2 × 90.000 = 180.000 < realisasi 200.000
    app(SimpanItemRevisi::class)->handle($revisi, $items, $this->k->adminPmat);

    expect(fn () => app(AjukanRevisiRab::class)->handle($revisi->fresh(), $this->k->adminPmat))
        ->toThrow(KesalahanDomain::class, 'tidak boleh kurang dari realisasinya');
});

test('menghapus item yang punya realisasi → ditolak', function () {
    [$data, $tautan] = dataRealisasi($this->p, '1', '100000');
    app(CatatRealisasi::class)->handle($this->p, $data, $tautan, $this->k->adminPmat);
    $revisi = app(BuatRevisiRab::class)->handle($this->p->fresh(), 'Ganti item konsumsi', $this->k->adminPmat);

    $baru = [['akun_id' => $this->k->akun->id, 'uraian' => 'Item lain', 'volume' => '1', 'satuan' => 'paket', 'harga_satuan' => '300000']];

    expect(fn () => app(SimpanItemRevisi::class)->handle($revisi, $baru, $this->k->adminPmat))
        ->toThrow(KesalahanDomain::class, 'tidak boleh dihapus');
});

test('ubah dan tambah item revisi: total dihitung ulang, asal tetap', function () {
    $revisi = app(BuatRevisiRab::class)->handle($this->p, 'Tambah item cetak', $this->k->adminPmat);
    $items = itemRevisi($revisi);
    $items[] = ['akun_id' => $this->k->akun->id, 'uraian' => 'Cetak modul', 'volume' => '10', 'satuan' => 'eks', 'harga_satuan' => '25000'];

    $revisi = app(SimpanItemRevisi::class)->handle($revisi, $items, $this->k->adminPmat);

    expect($revisi->total)->toBe('550000.00')
        ->and($revisi->item()->get()->pluck('asal_rab_item_id')->filter()->count())->toBe(1);
});

test('kenaikan melebihi sisa pagu → PaguTidakCukupException', function () {
    // Pagu 1.000.000, komitmen 300.000 → sisa 700.000.
    $revisi = app(BuatRevisiRab::class)->handle($this->p, 'Tambah item besar', $this->k->adminPmat);
    $items = itemRevisi($revisi);
    $items[] = ['akun_id' => $this->k->akun->id, 'uraian' => 'Sewa alat', 'volume' => '1', 'satuan' => 'paket', 'harga_satuan' => '700000.01'];
    app(SimpanItemRevisi::class)->handle($revisi, $items, $this->k->adminPmat);

    expect(fn () => app(AjukanRevisiRab::class)->handle($revisi->fresh(), $this->k->adminPmat))->toThrow(PaguTidakCukupException::class);

    $items[1]['harga_satuan'] = '700000';
    app(SimpanItemRevisi::class)->handle($revisi->fresh(), $items, $this->k->adminPmat);
    expect(app(AjukanRevisiRab::class)->handle($revisi->fresh(), $this->k->adminPmat)->status)->toBe(StatusRab::Diajukan);
});

test('realisasi baru ditolak selama revisi berjalan', function () {
    app(BuatRevisiRab::class)->handle($this->p, 'Penyesuaian jumlah peserta', $this->k->adminPmat);
    [$data, $tautan] = dataRealisasi($this->p->fresh(), '1', '100000');

    expect(fn () => app(CatatRealisasi::class)->handle($this->p->fresh(), $data, $tautan, $this->k->adminPmat))
        ->toThrow(KesalahanDomain::class, 'revisi RAB');
});

test('revisi draf tidak bisa disunting oleh unit lain atau setelah diajukan', function () {
    $revisi = app(BuatRevisiRab::class)->handle($this->p, 'Penyesuaian jumlah peserta', $this->k->adminPmat);
    $revisi = app(AjukanRevisiRab::class)->handle($revisi, $this->k->adminPmat);

    expect(fn () => app(SimpanItemRevisi::class)->handle($revisi, itemRevisi($revisi), $this->k->adminPmat))
        ->toThrow(AuthorizationException::class);
    expect(AjukanRevisiRab::peringatan($revisi))->toBe([]);
});
