<?php

namespace App\Http\Controllers;

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Support\KodeQr;
use App\Support\Pengaturan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Lembar pengajuan + RAB (PDF di-stream, tidak disimpan).
 */
class CetakPengajuanController extends Controller
{
    public function __invoke(Pengajuan $pengajuan): Response
    {
        Gate::authorize('view', $pengajuan);
        abort_if($pengajuan->status === StatusPengajuan::Draf, 404);

        $pengajuan->load(['unit', 'tahunAnggaran', 'rabAktif.item.akun', 'riwayatStatus.user']);

        $pdf = Pdf::loadView('pdf.pengajuan', [
            'pengajuan' => $pengajuan,
            'namaFakultas' => (string) Pengaturan::get('nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan'),
            'qr' => KodeQr::dataUri(route('verifikasi.pengajuan', $pengajuan->id)),
            'dicetakPada' => now()->locale('id'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('pengajuan-'.str_replace('/', '-', (string) $pengajuan->nomor).'.pdf');
    }
}
