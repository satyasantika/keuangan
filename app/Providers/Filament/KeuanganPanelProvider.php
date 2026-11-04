<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfil;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dasbor;
use App\Http\Middleware\WajibGantiSandi;
use App\Http\Middleware\WajibMfa;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class KeuanganPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('keuangan')
            ->path('panel')
            ->login(Login::class)
            ->profile(EditProfil::class, isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('Keuangan FKIP Unsil')],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(WajibMfa::class)
            ->brandName('Keuangan FKIP Unsil')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                Action::make('panduan')
                    ->label('Panduan')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->url(fn (): string => route('panduan.pengguna'))
                    ->extraAttributes(['rel' => 'noopener noreferrer'])
                    ->openUrlInNewTab(),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->spa()
            ->navigationGroups([
                'Beranda',
                'Kegiatan',
                'Bendahara',
                'Anggaran',
                'Laporan',
                'Master',
                'Sistem',
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render('<p class="mt-4 text-center text-sm"><a href="{{ asset(\'panduan/index.html\') }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Panduan Pengguna</a></p>'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('impersonasi.spanduk')->render(),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => Blade::render('<div class="py-3 text-center text-xs text-gray-500 dark:text-gray-400">Keuangan FKIP Unsil v{{ config(\'app.version\') }}</div>'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dasbor::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                WajibGantiSandi::class,
            ]);
    }
}
