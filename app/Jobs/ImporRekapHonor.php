<?php

namespace App\Jobs;

use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\RekapHonorEksternal;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ImporRekapHonorSelesai;
use App\Support\Uang;
use App\Support\VersiCacheDasbor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

/**
 * Impor rekap total honor ujian dari ekspor Excel fkipapp (BR-24). Semua baris satu batch, satu transaksi:
 * bila ada baris tidak valid seluruh batch ditolak. Berkas sementara SELALU dihapus (STANDAR-TEKNIS §1a).
 */
class ImporRekapHonor implements ShouldQueue
{
    use Queueable;

    /**
     * Pemetaan kolom berkas fkipapp → kolom sistem (sesuaikan bila format ekspor fkipapp berbeda).
     */
    public const KOLOM = [
        'tahun' => 'tahun',
        'bulan' => 'bulan',
        'kode_prodi' => 'kode_prodi',
        'jenis_ujian' => 'jenis_ujian',
        'jumlah_kegiatan' => 'jumlah_kegiatan',
        'jumlah_bruto' => 'jumlah_bruto',
        'jumlah_pajak' => 'jumlah_pajak',
        'jumlah_neto' => 'jumlah_neto',
    ];

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $jalur, public string $namaBerkas, public User $oleh)
    {
        $this->onQueue('impor');
    }

    public function handle(): void
    {
        $hasil = $this->proses();
        $this->oleh->notify(new ImporRekapHonorSelesai($hasil['batch'], $hasil['jumlah'], $hasil['galat']));
    }

    /**
     * @return array{batch: string|null, jumlah: int, galat: list<string>, peringatan: list<string>}
     */
    public function proses(): array
    {
        try {
            $baris = SimpleExcelReader::create($this->jalur)->getRows()->all();
            $galat = [];
            $peringatan = [];
            $siap = [];

            foreach (array_values($baris) as $i => $b) {
                $nomor = $i + 2; // baris 1 = header
                $nilai = fn (string $kolom) => trim((string) ($b[self::KOLOM[$kolom]] ?? ''));

                $tahun = TahunAnggaran::query()->where('tahun', (int) $nilai('tahun'))->first();
                $periode = $tahun === null ? null : Periode::query()->where('tahun_anggaran_id', $tahun->id)->where('bulan', (int) $nilai('bulan'))->first();

                if ($periode === null) {
                    $galat[] = "Baris {$nomor}: periode {$nilai('bulan')}/{$nilai('tahun')} tidak ada.";

                    continue;
                }

                if ($periode->status !== StatusPeriode::Terbuka) {
                    $galat[] = "Baris {$nomor}: periode {$periode->nama} terkunci.";

                    continue;
                }

                if (! in_array($nilai('jenis_ujian'), RekapHonorEksternal::JENIS_UJIAN, true)) {
                    $galat[] = "Baris {$nomor}: jenis ujian \"{$nilai('jenis_ujian')}\" tidak dikenal.";

                    continue;
                }

                $uang = [];

                foreach (['jumlah_bruto', 'jumlah_pajak', 'jumlah_neto'] as $kolom) {
                    if (! preg_match('/^\d+(\.\d{1,2})?$/', $nilai($kolom))) {
                        $galat[] = "Baris {$nomor}: {$kolom} bukan angka desimal yang sah.";

                        continue 2;
                    }

                    $uang[$kolom] = Uang::jumlahkan([$nilai($kolom)]);
                }

                if (Uang::bandingkan(Uang::kurang($uang['jumlah_bruto'], $uang['jumlah_pajak']), $uang['jumlah_neto']) !== 0) {
                    $galat[] = "Baris {$nomor}: neto harus sama dengan bruto − pajak.";

                    continue;
                }

                $unitId = Unit::query()->where('kode', strtoupper($nilai('kode_prodi')))->value('id');

                if ($unitId === null) {
                    $peringatan[] = "Baris {$nomor}: kode prodi \"{$nilai('kode_prodi')}\" tidak terpetakan ke unit.";
                }

                $siap[] = [
                    'tahun_anggaran_id' => $tahun->id,
                    'periode_id' => $periode->id,
                    'unit_id' => $unitId,
                    'jenis_ujian' => $nilai('jenis_ujian'),
                    'jumlah_kegiatan' => (int) $nilai('jumlah_kegiatan'),
                    ...$uang,
                ];
            }

            if ($galat !== [] || $siap === []) {
                return ['batch' => null, 'jumlah' => 0, 'galat' => $galat ?: ['Berkas tidak berisi baris data.'], 'peringatan' => $peringatan];
            }

            $batch = (string) Str::uuid7();

            DB::transaction(function () use ($siap, $batch): void {
                foreach ($siap as $data) {
                    RekapHonorEksternal::create([...$data, 'batch_impor' => $batch, 'nama_berkas_sumber' => $this->namaBerkas, 'diimpor_oleh' => $this->oleh->getKey()]);
                }
            });

            VersiCacheDasbor::naikkan();

            return ['batch' => $batch, 'jumlah' => count($siap), 'galat' => [], 'peringatan' => $peringatan];
        } catch (Throwable $e) {
            return ['batch' => null, 'jumlah' => 0, 'galat' => ['Berkas tidak dapat dibaca: '.Str::limit($e->getMessage(), 150)], 'peringatan' => []];
        } finally {
            File::delete($this->jalur);
        }
    }
}
