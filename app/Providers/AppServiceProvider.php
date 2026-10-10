<?php

namespace App\Providers;

use App\Models\Akun;
use App\Models\BarisImporGagal;
use App\Models\Ekspor;
use App\Models\Impor;
use App\Models\JenisPajak;
use App\Models\JenisPencairan;
use App\Models\KategoriPenggunaanDana;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\TahunAnggaran;
use App\Models\TarifPajak;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        ]);

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
