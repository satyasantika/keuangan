<?php

namespace App\Http\Controllers;

use App\Models\Rab;
use App\Queries\PerbandinganRabQuery;
use App\Support\Pengaturan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Lembar revisi RAB dengan tabel perbandingan (PDF di-stream).
 */
class CetakRevisiController extends Controller
{
    public function __invoke(Rab $rab): Response
    {
        Gate::authorize('view', $rab);
        abort_if($rab->nomor_revisi === 0, 404);

        $rab->load(['pengajuan.unit', 'pengajuan.tahunAnggaran', 'riwayatStatus.user']);

        return Pdf::loadView('pdf.revisi', [
            'rab' => $rab,
            'pengajuan' => $rab->pengajuan,
            'perbandingan' => PerbandinganRabQuery::bandingkan($rab),
            'namaFakultas' => (string) Pengaturan::get('nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan'),
            'dicetakPada' => now()->locale('id'),
        ])->setPaper('a4', 'portrait')->stream('revisi-'.$rab->nomor_revisi.'-'.str_replace('/', '-', (string) $rab->pengajuan->nomor).'.pdf');
    }
}
