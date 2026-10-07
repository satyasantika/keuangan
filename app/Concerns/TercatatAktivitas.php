<?php

namespace App\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mencatat perubahan nilai model ke activity_log (hanya atribut yang berubah).
 * Atribut rahasia tidak pernah dicatat.
 */
trait TercatatAktivitas
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([
                'password',
                'remember_token',
                'app_authentication_secret',
                'app_authentication_recovery_codes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }
}
