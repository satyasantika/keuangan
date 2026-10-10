<?php

namespace Tests\Support;

use App\Concerns\MenjagaPeriodeTerkunci;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ModelUji extends Model
{
    use HasUuids, MenjagaPeriodeTerkunci;

    protected $table = 'model_uji';

    protected $guarded = [];

    protected function kolomTanggalPeriode(): array
    {
        return ['tanggal'];
    }
}
