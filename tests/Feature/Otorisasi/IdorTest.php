<?php

use App\Actions\Pencairan\CatatPencairan;
use App\Actions\Pengembalian\CatatPengembalian;
use App\Actions\Revisi\BuatRevisiRab;
use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\TerimaSpj;
use App\Enums\Peran;
use App\Filament\Resources\Pencairans\Pages\ListPencairans;
use App\Filament\Resources\Pengajuans\Pages\ListPengajuans;
use App\Filament\Resources\Spjs\Pages\ListSpjs;
use App\Models\LaporanEkspor;
use App\Models\TautanBerkas;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * BR-21: admin-unit unit B tidak dapat membuka data unit A lewat URL ber-UUID, dan URL tautan bukti unit A
 * tidak pernah muncul di HTML/JSON Livewire yang diterima unit B.
 */
beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran();

    $this->p = buatPengajuanDisetujui($this->k);
    [$cair, $bukti] = dataPencairan('300000');
    $this->pencairan = app(CatatPencairan::class)->handle($this->p, $cair, $bukti, $this->k->bpp);
    [$d, $t] = dataRealisasi($this->p->fresh(), '1', '250000');
    $realisasi = app(CatatRealisasi::class)->handle($this->p->fresh(), $d, $t, $this->k->adminPmat);
    $this->spj = $realisasi->spj;
    app(TerimaSpj::class)->handle(app(AjukanSpj::class)->handle($this->spj, $this->k->adminPmat, true), $this->k->bpp);
    $this->pengembalian = app(CatatPengembalian::class)->handle($this->p->fresh(), ['tanggal_setor' => '2027-06-19', 'jumlah' => '50000', 'nomor_bukti_setor' => 'NTPN'], [
        ['label' => 'Bukti setor', 'url' => 'https://drive.google.com/file/d/1SetorIdor/view', 'akses_terbatas_dikonfirmasi' => true],
    ], $this->k->adminPmat);

    $this->revisi = app(BuatRevisiRab::class)->handle(buatPengajuanDisetujui($this->k), 'Penyesuaian jumlah peserta', $this->k->adminPmat);
    $this->ekspor = LaporanEkspor::create(['jenis' => 'LAP-01', 'parameter' => ['tahun_anggaran_id' => $this->k->tahun->id], 'format' => 'xlsx', 'diminta_oleh' => $this->k->adminPmat->id]);

    $this->penyerang = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);
});

test('admin-unit unit lain ditolak membuka record unit A lewat URL', function () {
    $url = [
        "/panel/pengajuan/{$this->p->id}",
        "/panel/pengajuan/{$this->p->id}/edit",
        "/panel/pencairan/{$this->pencairan->id}",
        "/panel/spj/{$this->spj->id}",
        "/panel/pengembalian/{$this->pengembalian->id}",
        "/panel/pagu/{$this->k->pagu->id}",
        "/panel/pagu/{$this->k->pagu->id}/edit",
        "/pengajuan/{$this->p->id}/pdf",
        "/pengajuan/{$this->p->id}/kartu",
        "/revisi/{$this->revisi->id}/pdf",
        URL::temporarySignedRoute('ekspor.unduh', now()->addHour(), ['laporanEkspor' => $this->ekspor->id], absolute: false),
    ];

    foreach ($url as $u) {
        $status = $this->actingAs($this->penyerang)->get($u)->getStatusCode();
        expect($status)->toBeIn([403, 404], "{$u} → {$status}");
    }

    // Pemilik unit tetap dapat membuka (kontrol).
    $this->actingAs($this->k->adminPmat)->get("/panel/pengajuan/{$this->p->id}")->assertOk();
});

test('URL tautan bukti unit A tidak muncul di HTML maupun JSON Livewire milik unit B', function () {
    $tautan = TautanBerkas::query()->pluck('url')->all();
    expect($tautan)->not->toBeEmpty();

    $halaman = collect(['/panel', '/panel/pengajuan', '/panel/pencairan', '/panel/spj', '/panel/pengembalian', '/panel/revisi-rab'])
        ->map(fn (string $u) => $this->actingAs($this->penyerang)->get($u)->assertOk()->getContent())
        ->implode("\n");

    foreach ([ListPengajuans::class, ListPencairans::class, ListSpjs::class] as $kelas) {
        $komponen = Livewire::actingAs($this->penyerang)->test($kelas);
        $halaman .= $komponen->html().json_encode($komponen->snapshot);
    }

    foreach ($tautan as $url) {
        expect(str_contains($halaman, $url))->toBeFalse("Bocor: {$url}")
            ->and(str_contains($halaman, (string) preg_replace('#^.*/d/([^/]+).*$#', '$1', $url)))->toBeFalse("Bocor id: {$url}");
    }
});

test('route record tidak menerima id numerik', function () {
    foreach (['/panel/pengajuan/1', '/panel/pengajuan/1/edit', '/panel/pencairan/1', '/panel/spj/1', '/panel/pengembalian/1', '/panel/pagu/1', '/pengajuan/1/pdf', '/revisi/1/pdf', '/verifikasi/1'] as $u) {
        expect($this->actingAs($this->k->keu)->get($u)->getStatusCode())->toBe(404, $u);
    }
});
