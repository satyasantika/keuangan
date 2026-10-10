<?php

use App\Actions\Spj\CatatPotonganPajak;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\HapusPotonganPajak;
use App\Enums\StatusSpj;
use App\Exceptions\KesalahanDomain;
use App\Models\JenisPajak;
use App\Models\TarifPajak;
use Database\Seeders\JenisPajakSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->seed(JenisPajakSeeder::class);
    $this->pph23 = JenisPajak::where('kode', 'pph23')->firstOrFail();
    $this->tarif = TarifPajak::create(['jenis_pajak_id' => $this->pph23->id, 'uraian' => 'Jasa', 'tarif_persen' => '2.00', 'berlaku_mulai' => '2027-01-01']);

    $p = buatPengajuanDicairkan($this->k, '1', '1000000');
    [$data, $tautan] = dataRealisasi($p, '1', '1000000');
    $this->realisasi = app(CatatRealisasi::class)->handle($p, $data, $tautan, $this->k->adminPmat);
    $this->catat = app(CatatPotonganPajak::class);
});

function dataPajak(string $jenisId, string $dasar = '1000000', string $nilai = '17500', array $timpa = []): array
{
    return ['jenis_pajak_id' => $jenisId, 'dasar_pengenaan' => $dasar, 'nilai' => $nilai, ...$timpa];
}

test('nilai tersimpan persis sesuai input — tidak ada perhitungan dari tarif', function () {
    $pp = $this->catat->handle($this->realisasi, dataPajak($this->pph23->id), $this->k->adminPmat);

    expect($pp->nilai)->toBe('17500.00')
        ->and($pp->tarif_referensi)->toBe('2.00')
        ->and($pp->tarif_pajak_id)->toBe($this->tarif->id)
        ->and($this->realisasi->fresh()->total_pajak)->toBe('17500.00')
        ->and($this->realisasi->fresh()->jumlah_neto)->toBe('982500.00')
        ->and($this->realisasi->spj->pengajuan->fresh()->total_pajak)->toBe('17500.00');
});

test('tarif referensi tersnapshot dan tidak berubah setelah master tarif diubah', function () {
    $pp = $this->catat->handle($this->realisasi, dataPajak($this->pph23->id), $this->k->adminPmat);

    $this->tarif->update(['tarif_persen' => '4.00']);

    expect($pp->fresh()->tarif_referensi)->toBe('2.00');
});

test('nilai > dasar pengenaan ditolak; dasar > bruto ditolak', function () {
    expect(fn () => $this->catat->handle($this->realisasi, dataPajak($this->pph23->id, '100000', '100000.01'), $this->k->adminPmat))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->catat->handle($this->realisasi, dataPajak($this->pph23->id, '1000000.01', '1'), $this->k->adminPmat))
        ->toThrow(ValidationException::class);
});

test('bpp melengkapi nomor bukti setor saat SPJ diajukan; admin-unit tidak bisa', function () {
    $pp = $this->catat->handle($this->realisasi, dataPajak($this->pph23->id), $this->k->adminPmat);
    $this->realisasi->spj->forceFill(['status' => StatusSpj::Diajukan])->save();
    $pp = $pp->fresh();

    expect(fn () => $this->catat->handle($this->realisasi->fresh(), dataPajak($this->pph23->id, timpa: ['nomor_bukti_setor' => 'NTPN-1']), $this->k->adminPmat, $pp))
        ->toThrow(AuthorizationException::class);

    $this->catat->handle($this->realisasi->fresh(), dataPajak($this->pph23->id, timpa: ['nomor_bukti_setor' => 'NTPN-1', 'tanggal_setor' => '2027-06-19']), $this->k->bpp, $pp);
    expect($pp->fresh()->nomor_bukti_setor)->toBe('NTPN-1');

    expect(fn () => $this->catat->handle($this->realisasi->fresh(), dataPajak($this->pph23->id, nilai: '20000'), $this->k->bpp, $pp->fresh()))
        ->toThrow(KesalahanDomain::class);
});

test('hapus potongan memperbarui jumlah neto', function () {
    $pp = $this->catat->handle($this->realisasi, dataPajak($this->pph23->id), $this->k->adminPmat);

    app(HapusPotonganPajak::class)->handle($pp, $this->k->adminPmat);

    expect($this->realisasi->fresh()->jumlah_neto)->toBe('1000000.00')
        ->and($this->realisasi->fresh()->total_pajak)->toBe('0.00');
});

test('tanpa tarif berlaku → tarif referensi kosong, nilai tetap tersimpan', function () {
    $ppn = JenisPajak::where('kode', 'ppn')->firstOrFail();
    $pp = $this->catat->handle($this->realisasi, dataPajak($ppn->id, '500000', '55000'), $this->k->adminPmat);

    expect($pp->tarif_referensi)->toBeNull()->and($pp->nilai)->toBe('55000.00');
});
