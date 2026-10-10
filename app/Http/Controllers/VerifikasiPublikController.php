<?php

namespace App\Http\Controllers;

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use Illuminate\Contracts\View\View;

/**
 * Halaman publik hasil pindai QR: hanya data ringkas, tanpa rincian item/penerima/tautan.
 */
class VerifikasiPublikController extends Controller
{
    public function __invoke(string $id): View
    {
        $pengajuan = Pengajuan::query()->with('unit')->find($id);

        abort_if($pengajuan === null || $pengajuan->status === StatusPengajuan::Draf || $pengajuan->nomor === null, 404);

        return view('publik.verifikasi', ['pengajuan' => $pengajuan]);
    }
}
