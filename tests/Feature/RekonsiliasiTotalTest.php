<?php

use App\Actions\Pencairan\CatatPencairan;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran();
    $this->p = buatPengajuanDisetujui($this->k);
    [$data, $tautan] = dataPencairan('200000');
    app(CatatPencairan::class)->handle($this->p, $data, $tautan, $this->k->bpp);
});

test('tanpa selisih → sukses', function () {
    $this->artisan('keuangan:rekonsiliasi')
        ->expectsOutput('Tidak ada selisih total.')
        ->assertSuccessful();
});

test('selisih dilaporkan tanpa menyimpan; --perbaiki menyimpan hasil hitung ulang', function () {
    $this->p->fresh()->forceFill(['total_dicairkan' => '1.00'])->saveQuietly();

    $this->artisan('keuangan:rekonsiliasi')
        ->expectsTable(['Pengajuan', 'Kolom', 'Tersimpan', 'Hitung ulang', 'Selisih'], [
            ['PGJ/2027/0001', 'total_dicairkan', '1.00', '200000.00', '199999.00'],
        ])
        ->assertFailed();

    expect($this->p->fresh()->total_dicairkan)->toBe('1.00');

    $this->artisan('keuangan:rekonsiliasi --perbaiki')->assertSuccessful();

    expect($this->p->fresh()->total_dicairkan)->toBe('200000.00');
    $this->artisan('keuangan:rekonsiliasi')->assertSuccessful();
});
