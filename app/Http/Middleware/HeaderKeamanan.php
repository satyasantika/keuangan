<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan global.
 *
 * CSP dasar kompatibel Filament/Livewire/Alpine (skrip inline & evaluasi Alpine), pratinjau tautan hanya dari
 * Google Drive/Docs (frame) dan lh3.googleusercontent.com (gambar).
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=()');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->csp());
        }

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    public static function csp(): string
    {
        $vite = Vite::isRunningHot() ? ' '.trim((string) file_get_contents(public_path('hot'))) : '';
        $viteWs = $vite === '' ? '' : ' '.preg_replace('#^http#', 'ws', trim($vite));

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$vite,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net".$vite,
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob: https://lh3.googleusercontent.com https://ui-avatars.com",
            'frame-src https://drive.google.com https://docs.google.com',
            "connect-src 'self'".$vite.$viteWs,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
