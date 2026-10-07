<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login as LoginDasar;
use Filament\Notifications\Notification;

/**
 * Pembatasan login 5 percobaan/menit per kombinasi surel + IP (F2.6).
 */
class Login extends LoginDasar
{
    /**
     * @param  string|null  $method
     * @param  string|null  $component
     */
    protected function getRateLimitKey($method, $component = null): string
    {
        $email = mb_strtolower(trim((string) ($this->data['email'] ?? '')));

        return 'login-panel:'.hash('sha256', $email.'|'.request()->ip());
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->title('Terlalu banyak percobaan masuk')
            ->body("Coba lagi dalam {$exception->secondsUntilAvailable} detik.")
            ->danger();
    }
}
