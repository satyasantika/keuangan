<?php

namespace App\Models;

use Filament\Actions\Imports\Models\FailedImportRow;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Baris impor yang gagal validasi, dengan kunci UUIDv7.
 */
class BarisImporGagal extends FailedImportRow
{
    use HasUuids;

    protected $table = 'failed_import_rows';
}
