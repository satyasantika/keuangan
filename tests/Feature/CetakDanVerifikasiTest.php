<?php

use App\Enums\Peran;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->k = siapkanAnggaran();
    $this->pengajuan = buatPengajuanDiajukan($this->k);
});

test('PDF dapat dibuat untuk pemilik (application/pdf)', function () {
    $respons = $this->actingAs($this->k->adminPmat)->get("/pengajuan/{$this->pengajuan->id}/pdf");

    $respons->assertOk();
    expect($respons->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respons->headers->get('Content-Disposition'))->toContain('pengajuan-PGJ-2027-0001.pdf')
        ->and(substr($respons->getContent(), 0, 4))->toBe('%PDF');
});

test('PDF: unit lain 403, tamu dialihkan ke login, draf 404', function () {
    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->k->pbio->id]);

    $this->actingAs($adminPbio)->get("/pengajuan/{$this->pengajuan->id}/pdf")->assertForbidden();

    $draf = buatPengajuanDraf($this->k);
    $this->actingAs($this->k->adminPmat)->get("/pengajuan/{$draf->id}/pdf")->assertNotFound();

    auth()->logout();
    $this->get("/pengajuan/{$this->pengajuan->id}/pdf")->assertRedirect();
});

test('tombol Cetak PDF tampil untuk pengajuan bukan draf', function () {
    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $this->pengajuan->getRouteKey()])
        ->assertActionVisible('cetakPdf');

    $draf = buatPengajuanDraf($this->k);
    Livewire::actingAs($this->k->adminPmat)->test(ViewPengajuan::class, ['record' => $draf->getRouteKey()])
        ->assertActionHidden('cetakPdf');
});

test('halaman verifikasi menampilkan nomor tanpa uraian item', function () {
    $this->get("/verifikasi/{$this->pengajuan->id}")
        ->assertOk()
        ->assertSee('PGJ/2027/0001')
        ->assertSee('Pendidikan Matematika')
        ->assertSee('Dokumen ini tercatat di Keuangan FKIP Unsil')
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertDontSee('Konsumsi peserta')
        ->assertDontSee('drive.google.com');
});

test('verifikasi: draf → 404; UUID acak → 404', function () {
    $draf = buatPengajuanDraf($this->k);

    $this->get("/verifikasi/{$draf->id}")->assertNotFound();
    $this->get('/verifikasi/'.Str::uuid7())->assertNotFound();
    $this->get('/verifikasi/bukan-uuid')->assertNotFound();
});

test('verifikasi: percobaan ke-31 dalam semenit → 429', function () {
    for ($i = 1; $i <= 30; $i++) {
        $this->get("/verifikasi/{$this->pengajuan->id}")->assertOk();
    }

    $this->get("/verifikasi/{$this->pengajuan->id}")->assertStatus(429);
});
