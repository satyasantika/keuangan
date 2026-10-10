<?php

namespace App\Http\Controllers;

use App\Models\LaporanEkspor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh berkas ekspor sementara: tautan bertanda tangan + hanya peminta; 410 bila kedaluwarsa/terhapus.
 */
class UnduhEksporController extends Controller
{
    public function __invoke(LaporanEkspor $laporanEkspor): BinaryFileResponse
    {
        abort_unless(request()->user()?->getKey() === $laporanEkspor->diminta_oleh, 403);
        abort_unless($laporanEkspor->dapatDiunduh(), 410, 'Berkas ekspor sudah kedaluwarsa atau dihapus.');

        return response()->download(storage_path('app/'.$laporanEkspor->path), strtolower($laporanEkspor->jenis).'-'.$laporanEkspor->id.'.'.$laporanEkspor->format);
    }
}
