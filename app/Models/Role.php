<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as RoleDasar;

/**
 * Peran dengan primary key UUIDv7.
 */
class Role extends RoleDasar
{
    use HasUuids;

    protected $table = 'roles';
}
