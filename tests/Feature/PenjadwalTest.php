<?php

use App\Actions\Spj\AjukanSpj;
use App\Actions\Spj\CatatRealisasi;
use App\Actions\Spj\TerimaSpj;
use App\Enums\Peran;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\TautanBerkas;
use App\Models\TautanBerkasBeku;
use App\Notifications\PengingatBatasSpj;
use App\Notifications\PengingatKunciPeriode;
use App\Notifications\RekapMingguan;
use App\Notifications\SelisihRekonsiliasi;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo('2027-06-20 10:00:00');
    $this->k = siapkanAnggaran('100000000.00');
    $this->p = buatPengajuanDicairkan($this->k); // batas SPJ 2027-06-29
    Notification::fake();
});

test('batas SPJ tersimpan sesuai skenario', function () {
    expect($this->p->batas_spj->toDateString())->toBe('2027-06-29');
});

test('H-3 dan H-0 mengirim, H-2 tidak', function (string $tanggal, bool $kirim) {
    $this->travelTo("{$tanggal} 07:00:00");
    $this->artisan('keuangan:pengingat-spj')->assertSuccessful();

    $kirim
        ? Notification::assertSentTo([$this->k->adminPmat, $this->k->kepalaPmat], PengingatBatasSpj::class)
        : Notification::assertNothingSent();
})->with([
    'H-3' => ['2027-06-26', true],
    'H-2' => ['2027-06-27', false],
    'H-0' => ['2027-06-29', true],
]);

test('terlambat 1 hari tidak mengirim (interval 3); terlambat 3 hari mengirim + tembusan admin-keuangan', function () {
    $this->travelTo('2027-06-30 07:00:00');
    $this->artisan('keuangan:pengingat-spj')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo('2027-07-02 07:00:00');
    $this->artisan('keuangan:pengingat-spj')->assertSuccessful();
    Notification::assertSentTo([$this->k->adminPmat, $this->k->keu], PengingatBatasSpj::class, fn ($n) => $n->hariMenujuBatas === -3);
});

test('SPJ sudah diajukan tidak mengirim; --dry-run tidak mengirim', function () {
    $this->travelTo('2027-06-26 07:00:00');
    $this->artisan('keuangan:pengingat-spj --dry-run')->expectsOutputToContain('[dry-run] 1 pengingat.')->assertSuccessful();
    Notification::assertNothingSent();

    [$data, $tautan] = dataRealisasi($this->p, '1', '100000');
    $spj = app(CatatRealisasi::class)->handle($this->p, $data, $tautan, $this->k->adminPmat)->spj;
    app(AjukanSpj::class)->handle($spj, $this->k->adminPmat, true);
    Notification::fake();

    $this->artisan('keuangan:pengingat-spj')->assertSuccessful();
    Notification::assertNotSentTo($this->k->adminPmat, PengingatBatasSpj::class);
});

test('pengingat kunci hanya pada tanggal yang diatur dan bila periode bulan lalu terbuka', function () {
    $this->travelTo('2027-07-04 08:00:00');
    $this->artisan('keuangan:pengingat-kunci-periode')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo('2027-07-05 08:00:00');
    $this->artisan('keuangan:pengingat-kunci-periode')->assertSuccessful();
    Notification::assertSentTo($this->k->keu, PengingatKunciPeriode::class, fn ($n) => $n->periode->bulan === 6);
});

test('rekap mingguan terkirim ke peran keuangan; rekonsiliasi berselisih memberi tahu super-admin', function () {
    $this->artisan('keuangan:rekap-mingguan')->assertSuccessful();
    Notification::assertSentTo([$this->k->keu, $this->k->bpp, $this->k->wadek], RekapMingguan::class);

    $admin = penggunaDenganPeran(Peran::SuperAdmin);
    $this->p->fresh()->forceFill(['total_dicairkan' => '1.00'])->saveQuietly();
    $this->artisan('keuangan:rekonsiliasi')->assertFailed();
    Notification::assertSentTo($admin, SelisihRekonsiliasi::class);
});

test('jadwal terdaftar untuk semua perintah', function () {
    $perintah = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');

    foreach (['keuangan:pengingat-spj', 'keuangan:rekap-mingguan', 'keuangan:pengingat-kunci-periode', 'keuangan:rekonsiliasi', 'keuangan:periksa-ulang-tautan', 'keuangan:bersihkan-sementara'] as $nama) {
        expect($perintah)->toContain($nama);
    }
});

test('periksa-ulang-tautan tidak menyentuh tautan yang sudah dibekukan', function () {
    $spj = buatSpjDenganRealisasi($this->k, '300000');
    app(TerimaSpj::class)->handle(app(AjukanSpj::class)->handle($spj, $this->k->adminPmat, true), $this->k->bpp);
    $tidakBeku = TautanBerkas::query()->whereNotIn('id', TautanBerkasBeku::query()->select('tautan_berkas_id'))->count();
    TautanBerkas::query()->update(['dicek_pada' => null]);

    Bus::fake([PeriksaTautanBerkas::class]);
    $this->artisan('keuangan:periksa-ulang-tautan')->assertSuccessful();

    Bus::assertDispatchedTimes(PeriksaTautanBerkas::class, $tidakBeku);
    Bus::assertNotDispatched(PeriksaTautanBerkas::class, fn ($job) => TautanBerkasBeku::where('tautan_berkas_id', $job->tautan->id)->exists());
});

test('bersihkan-sementara menghapus berkas 25 jam dan mempertahankan yang 1 jam', function () {
    $folder = storage_path('app/tmp/ekspor');
    File::ensureDirectoryExists($folder);
    $lama = $folder.'/uji-lama-'.uniqid().'.xlsx';
    $baru = $folder.'/uji-baru-'.uniqid().'.xlsx';
    File::put($lama, 'x');
    File::put($baru, 'y');
    touch($lama, now()->subHours(25)->getTimestamp());
    touch($baru, now()->subHour()->getTimestamp());

    $this->artisan('keuangan:bersihkan-sementara')->assertSuccessful();

    expect(File::exists($lama))->toBeFalse()->and(File::exists($baru))->toBeTrue();
    File::delete($baru);
});
