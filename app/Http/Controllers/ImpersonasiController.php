<?php

namespace App\Http\Controllers;

use App\Actions\Pengguna\AkhiriImpersonasi;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class ImpersonasiController extends Controller
{
    public function berhenti(AkhiriImpersonasi $aksi): RedirectResponse
    {
        $aksi->handle();

        return redirect()->to(Filament::getPanel('keuangan')->getUrl().'/users');
    }
}
