<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Menampilkan panduan pengguna (docs/panduan/*.md) sebagai HTML ter-escape untuk pengguna yang login.
 */
class PanduanController extends Controller
{
    public function __invoke(Request $request, ?string $halaman = null): View
    {
        /** @var User $pengguna */
        $pengguna = $request->user();
        $tersedia = self::halamanTersedia();
        $halaman ??= in_array($peran = (string) $pengguna->getRoleNames()->first(), $tersedia, true) ? $peran : 'README';

        abort_unless(in_array($halaman, $tersedia, true), 404);

        $html = Str::markdown((string) file_get_contents(self::folder().'/'.$halaman.'.md'), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        // Tautan antar-berkas panduan (foo.md) diarahkan ke route panduan.
        $html = (string) preg_replace_callback('/href="([a-z0-9-]+|README)\.md"/i', fn (array $c): string => 'href="'.e(route('panduan.pengguna', $c[1])).'"', $html);

        return view('panduan.halaman', ['isi' => $html, 'halaman' => $halaman, 'daftar' => $tersedia]);
    }

    /**
     * @return list<string>
     */
    public static function halamanTersedia(): array
    {
        return array_map(fn (string $berkas): string => basename($berkas, '.md'), glob(self::folder().'/*.md') ?: []);
    }

    private static function folder(): string
    {
        return base_path('docs/panduan');
    }
}
