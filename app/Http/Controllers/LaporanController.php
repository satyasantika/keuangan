<?php

namespace App\Http\Controllers;

use App\Filament\Resources\LaporanEkspors\LaporanEksporResource;
use App\Jobs\BuatEksporLaporan;
use App\Laporan\KatalogLaporan;
use App\Laporan\PenulisLaporan;
use App\Models\LaporanEkspor;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ekspor laporan: kecil (≤ 2.000 baris) di-stream langsung tanpa berkas tersimpan; besar diantrekan.
 */
class LaporanController extends Controller
{
    public function ekspor(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(KatalogLaporan::semua()))],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'tahun_anggaran_id' => ['required', 'string'],
            'jenis_periode' => ['nullable', Rule::in(['bulanan', 'semester', 'tahunan'])],
            'nilai_periode' => ['nullable', 'integer', 'between:1,12'],
            'unit_id' => ['nullable', 'string'],
            'pengajuan_id' => ['nullable', 'string'],
            'tahun_ts' => ['nullable', 'integer'],
        ]);

        /** @var User $pengguna */
        $pengguna = $request->user();
        $parameter = array_diff_key($data, array_flip(['jenis', 'format']));

        try {
            $laporan = KatalogLaporan::bangun($data['jenis'], $parameter, $pengguna);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        if (count($laporan->baris) > KatalogLaporan::BATAS_STREAM) {
            $ekspor = LaporanEkspor::create(['jenis' => $data['jenis'], 'parameter' => $parameter, 'format' => $data['format'], 'diminta_oleh' => $pengguna->getKey()]);
            BuatEksporLaporan::dispatch($ekspor);
            Notification::make()->title('Ekspor diantrekan')->body('Notifikasi akan muncul saat berkas siap diunduh.')->success()->send();

            return redirect()->to(LaporanEksporResource::getUrl('index'));
        }

        if ($data['format'] === 'pdf') {
            return PenulisLaporan::pdf($laporan, $pengguna)->stream($laporan->namaBerkas('pdf'));
        }

        return response()->streamDownload(function () use ($laporan): void {
            $sementara = sys_get_temp_dir().'/lap-'.Str::uuid7().'.xlsx';

            try {
                PenulisLaporan::tulisXlsx($laporan, $sementara);
                readfile($sementara);
            } finally {
                @unlink($sementara);
            }
        }, $laporan->namaBerkas('xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
