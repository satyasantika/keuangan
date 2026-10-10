<?php

namespace App\Providers;

use App\Contracts\PenyimpananBerkas;
use App\Models\Akun;
use App\Models\BarisImporGagal;
use App\Models\Ekspor;
use App\Models\Impor;
use App\Models\JenisPajak;
use App\Models\JenisPencairan;
use App\Models\KategoriPenggunaanDana;
use App\Models\Pagu;
use App\Models\PaguAkun;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\TahunAnggaran;
use App\Models\TarifPajak;
use App\Models\TautanBerkas;
use App\Models\Unit;
use App\Models\User;
use App\Services\Berkas\TautanEksternal;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Model impor/ekspor Filament memakai UUIDv7.
        $this->app->bind(Import::class, Impor::class);
        $this->app->bind(FailedImportRow::class, BarisImporGagal::class);
        $this->app->bind(Export::class, Ekspor::class);

        // Kebijakan berkas (STANDAR-TEKNIS §1a.7): hanya mode tautan yang didukung saat ini.
        $this->app->bind(PenyimpananBerkas::class, function ($app): PenyimpananBerkas {
            return match (config('berkas.mode')) {
                'tautan' => $app->make(TautanEksternal::class),
                default => throw new RuntimeException('Mode berkas belum didukung: '.config('berkas.mode')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        DB::prohibitDestructiveCommands(app()->isProduction());

        // Alias morph wajib; alias lain ditambahkan saat modelnya dibuat.
        Relation::enforceMorphMap([
            'user' => User::class,
            'tahun_anggaran' => TahunAnggaran::class,
            'periode' => Periode::class,
            'unit' => Unit::class,
            'akun' => Akun::class,
            'kategori_penggunaan_dana' => KategoriPenggunaanDana::class,
            'jenis_pencairan' => JenisPencairan::class,
            'jenis_pajak' => JenisPajak::class,
            'tarif_pajak' => TarifPajak::class,
            'pengaturan' => Pengaturan::class,
            'pagu' => Pagu::class,
            'pagu_akun' => PaguAkun::class,
            'pengajuan' => Pengajuan::class,
            'rab' => Rab::class,
            'rab_item' => RabItem::class,
            'tautan_berkas' => TautanBerkas::class,
        ]);

        // Permintaan HTTP keluar ke Drive dibatasi global (02-ARSITEKTUR §3.4).
        RateLimiter::for('periksa-tautan', fn () => Limit::perMinute(60));
        RateLimiter::for('verifikasi-publik', fn (Request $request) => Limit::perMinute(30)->by((string) $request->ip()));

        $this->siapkanSubpath();
    }

    /**
     * Aplikasi berjalan di bawah awalan path (mis. /keuangan) di belakang reverse proxy
     * (STANDAR-TEKNIS §2.4): semua URL dibangkitkan dengan awalan itu.
     */
    private function siapkanSubpath(): void
    {
        $appUrl = (string) config('app.url');

        $path = parse_url($appUrl, PHP_URL_PATH);

        if ($path !== null && $path !== '' && $path !== '/') {
            URL::forceRootUrl($appUrl);
        }

        if (parse_url($appUrl, PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }
    }
}
