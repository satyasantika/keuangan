<?php

namespace App\Http\Controllers;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mengarahkan pengguna ke panduan HTML perannya di public/panduan.
 */
class PanduanController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $pengguna */
        $pengguna = $request->user();
        $peran = (string) $pengguna->getRoleNames()->first();
        $halaman = Peran::tryFrom($peran) !== null && is_file(public_path("panduan/{$peran}.html")) ? "{$peran}.html" : 'index.html';

        return redirect()->to(asset("panduan/{$halaman}"));
    }
}
