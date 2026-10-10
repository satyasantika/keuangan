<?php

namespace App\Models;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Catatan impor Filament dengan kunci UUIDv7.
 */
class Impor extends Import
{
    use HasUuids;

    protected $table = 'imports';
}
