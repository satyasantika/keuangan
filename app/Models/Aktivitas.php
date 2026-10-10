<?php

namespace App\Models;

use App\Support\Impersonasi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity;

/**
 * Entri jejak audit dengan primary key UUIDv7.
 */
class Aktivitas extends Activity
{
    use HasUuids;

    protected $table = 'activity_log';

    /**
     * Selama impersonasi darurat, setiap entri audit memuat id super-admin asal.
     */
    protected static function booted(): void
    {
        static::creating(function (self $aktivitas): void {
            $asal = Impersonasi::idAsal();

            if ($asal !== null) {
                $properti = $aktivitas->properties;
                $aktivitas->properties = $properti->put('diimpersonasi_oleh', $asal);
            }
        });
    }
}
