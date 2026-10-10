<?php

use App\Actions\Berkas\HapusTautanBerkas;
use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\Pengajuans\Pages\ViewPengajuan;
use App\Filament\Resources\Pengajuans\RelationManagers\TautanBerkasRelationManager;
use App\Models\Akun;
use App\Models\TautanBerkas;
use App\Models\Unit;
use App\Rules\TautanBerkasValid;
use App\Services\Berkas\PenguraiTautanDrive;
use App\Services\Berkas\TautanEksternal;
use Database\Seeders\UnitContohSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    // Pemeriksaan keteraksesan: berkas ada dan aksesnya terbatas.
    Http::fake(['*' => Http::response('', 403)]);
    $this->seed(UnitContohSeeder::class);
    $this->tahun = buatTahun(2027);
    $this->pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $this->adminPmat = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => $this->pmat->id]);
    $this->pengajuan = app(SimpanDrafPengajuan::class)->handle(dataKegiatan($this->tahun->id), [itemRab(Akun::factory()->create()->id)], $this->adminPmat);
    $this->simpan = app(SimpanTautanBerkas::class);
});

test('validasi tautan: url sah dan tidak sah', function (string $url, bool $sah) {
    expect(TautanBerkasValid::sah($url))->toBe($sah);
})->with([
    'drive file view' => ['https://drive.google.com/file/d/1AbC_dEf-123/view?usp=sharing', true],
    'docs document' => ['https://docs.google.com/document/d/1XyZ987/edit', true],
    'subdomain unsil' => ['https://lppm.unsil.ac.id/x.pdf', true],
    'http' => ['http://drive.google.com/file/d/1AbC/view', false],
    'bit.ly' => ['https://bit.ly/3abc', false],
    'folder drive' => ['https://drive.google.com/drive/folders/1Folder123', false],
    'folder drive u/0' => ['https://drive.google.com/drive/u/0/folders/1Folder123', false],
    'dropbox' => ['https://www.dropbox.com/s/abc/x.pdf', false],
    'domain mirip' => ['https://unsil.ac.id.evil.com/x.pdf', false],
    'akhiran mirip' => ['https://evilunsil.ac.id/x.pdf', false],
    'userinfo' => ['https://drive.google.com@evil.com/file/d/1/view', false],
    'kosong' => ['', false],
]);

test('pengurai mengekstrak drive_file_id dan penyedia', function () {
    $urai = app(PenguraiTautanDrive::class);

    expect($urai->urai('https://drive.google.com/file/d/1AbC_dEf-123/view'))->toBe(['penyedia' => PenyediaTautan::GoogleDrive, 'drive_file_id' => '1AbC_dEf-123', 'folder' => false])
        ->and($urai->urai('https://drive.google.com/open?id=1Open')['drive_file_id'])->toBe('1Open')
        ->and($urai->urai('https://drive.google.com/uc?id=1Uc&export=download')['drive_file_id'])->toBe('1Uc')
        ->and($urai->urai('https://docs.google.com/spreadsheets/d/1Sheet/edit')['drive_file_id'])->toBe('1Sheet')
        ->and($urai->urai('https://docs.google.com/presentation/d/1Pres/edit')['penyedia'])->toBe(PenyediaTautan::GoogleDocs)
        ->and($urai->urai('https://lppm.unsil.ac.id/x.pdf'))->toBe(['penyedia' => PenyediaTautan::Unsil, 'drive_file_id' => null, 'folder' => false]);
});

test('simpan tautan TOR Drive mengisi penyedia dan drive_file_id tanpa konfirmasi akses', function () {
    $tautan = $this->simpan->handle($this->pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorFile/view', 'TOR Lokakarya', false, $this->adminPmat);

    expect($tautan->penyedia)->toBe(PenyediaTautan::GoogleDrive)
        ->and($tautan->drive_file_id)->toBe('1TorFile')
        ->and($tautan->pemilik->is($this->pengajuan))->toBeTrue()
        ->and($this->pengajuan->tautanJenis(JenisTautan::Tor)->count())->toBe(1)
        ->and(app(PenyimpananBerkas::class))->toBeInstanceOf(TautanEksternal::class);
});

test('bukti keuangan tanpa konfirmasi akses terbatas ditolak', function () {
    try {
        $this->simpan->handle($this->pengajuan, JenisTautan::Kuitansi, 'https://drive.google.com/file/d/1Kw/view', 'Kuitansi', false, $this->adminPmat);
        $this->fail('Seharusnya ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors()['akses_terbatas_dikonfirmasi'][0])->toContain('NPWP/rekening');
    }

    $ok = $this->simpan->handle($this->pengajuan, JenisTautan::Kuitansi, 'https://drive.google.com/file/d/1Kw/view', 'Kuitansi', true, $this->adminPmat);
    expect($ok->akses_terbatas_dikonfirmasi)->toBeTrue();
});

test('tautan tidak sah ditolak oleh action', function () {
    expect(fn () => $this->simpan->handle($this->pengajuan, JenisTautan::Tor, 'https://bit.ly/x', 'TOR', false, $this->adminPmat))
        ->toThrow(ValidationException::class);
});

test('admin-unit unit lain tidak dapat melihat atau menambah tautan', function () {
    $tautan = $this->simpan->handle($this->pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorFile/view', 'TOR', false, $this->adminPmat);
    $adminPbio = penggunaDenganPeran(Peran::AdminUnit, ['unit_id' => Unit::where('kode', 'PBIO')->value('id')]);

    expect($adminPbio->can('view', $tautan))->toBeFalse()
        ->and($this->adminPmat->can('view', $tautan))->toBeTrue()
        ->and(TautanBerkasRelationManager::canViewForRecord($this->pengajuan, ViewPengajuan::class))->toBeFalse();

    $this->actingAs($adminPbio)->get("/panel/pengajuan/{$this->pengajuan->id}")->assertForbidden();

    expect(fn () => $this->simpan->handle($this->pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1Lain/view', 'TOR', false, $adminPbio))
        ->toThrow(AuthorizationException::class);
});

test('setelah pengajuan diajukan tautan TOR tidak bisa dihapus atau diubah', function () {
    $tautan = $this->simpan->handle($this->pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1TorFile/view', 'TOR', false, $this->adminPmat);
    $this->pengajuan->forceFill(['status' => StatusPengajuan::Diajukan])->save();
    $tautan->refresh();

    expect(fn () => $tautan->update(['url' => 'https://drive.google.com/file/d/1Baru/view']))->toThrow(KesalahanDomain::class, 'terkunci')
        ->and(fn () => $tautan->fresh()->delete())->toThrow(KesalahanDomain::class, 'terkunci')
        ->and(fn () => app(HapusTautanBerkas::class)->handle($tautan->fresh(), $this->adminPmat))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->simpan->handle($this->pengajuan->fresh(), JenisTautan::Tor, 'https://drive.google.com/file/d/1X/view', 'TOR 2', false, $this->adminPmat))
        ->toThrow(AuthorizationException::class);

    // Hasil pemeriksaan tetap boleh diperbarui.
    $segar = $tautan->fresh();
    $segar->forceFill(['status_cek' => 'dapat_diakses', 'dicek_pada' => now()])->save();
    expect($segar->fresh()->status_cek->value)->toBe('dapat_diakses');
});

test('perubahan URL tercatat activitylog', function () {
    $tautan = $this->simpan->handle($this->pengajuan, JenisTautan::Tor, 'https://drive.google.com/file/d/1Lama/view', 'TOR', false, $this->adminPmat);
    $tautan->update(['url' => 'https://drive.google.com/file/d/1Baru/view']);

    $log = Activity::where('subject_type', 'tautan_berkas')->where('subject_id', $tautan->id)->where('event', 'updated')->get()
        ->first(fn (Activity $a) => isset($a->attribute_changes['old']['url']));
    expect($log->attribute_changes['old']['url'])->toBe('https://drive.google.com/file/d/1Lama/view')
        ->and($log->attribute_changes['attributes']['url'])->toBe('https://drive.google.com/file/d/1Baru/view');
});

test('relation manager: admin-unit menambah TOR dari halaman pengajuan', function () {
    Livewire::actingAs($this->adminPmat)
        ->test(TautanBerkasRelationManager::class, ['ownerRecord' => $this->pengajuan, 'pageClass' => ViewPengajuan::class])
        ->callTableAction('create', data: ['jenis' => 'tor', 'label' => 'TOR', 'url' => 'https://drive.google.com/file/d/1Rm/view'])
        ->assertHasNoTableActionErrors();

    Livewire::actingAs($this->adminPmat)
        ->test(TautanBerkasRelationManager::class, ['ownerRecord' => $this->pengajuan, 'pageClass' => ViewPengajuan::class])
        ->callTableAction('create', data: ['jenis' => 'tor', 'label' => 'TOR', 'url' => 'https://drive.google.com/drive/folders/1F'])
        ->assertHasTableActionErrors(['url']);

    expect(TautanBerkas::count())->toBe(1);
});

test('mode berkas selain tautan ditolak', function () {
    config(['berkas.mode' => 's3']);

    expect(fn () => app(PenyimpananBerkas::class))->toThrow(RuntimeException::class, 'Mode berkas belum didukung');
});
