<?php

namespace App\Providers;

use App\Models\Periode;
use App\Models\TahunAnggaran;
use App\Models\User;
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
        //
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
