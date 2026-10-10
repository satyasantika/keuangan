<?php

namespace App\Http\Controllers;

use App\Models\Pengajuan;
use App\Support\Pengaturan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * LAP-03 Kartu kegiatan (buku pembantu per pengajuan), PDF di-stream; tanpa berkas bukti.
 */
class CetakKartuKegiatanController extends Controller
{
    public function __invoke(Pengajuan $pengajuan): Response
    {
        Gate::authorize('view', $pengajuan);

        $pengajuan->load([
            'unit', 'tahunAnggaran', 'rab', 'rabAktif.item.akun', 'pencairan.jenisPencairan', 'spj.realisasiItem.rabItem',
            'spj.realisasiItem.potonganPajak.jenisPajak', 'pengembalian', 'riwayatStatus.user',
        ]);

        return Pdf::loadView('pdf.kartu-kegiatan', [
            'pengajuan' => $pengajuan,
            'namaFakultas' => (string) Pengaturan::get('nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan'),
            'pencetak' => request()->user()?->name,
            'dicetakPada' => now()->locale('id'),
        ])->setPaper('a4', 'portrait')->stream('kartu-kegiatan-'.str_replace('/', '-', (string) ($pengajuan->nomor ?? $pengajuan->id)).'.pdf');
    }
}
