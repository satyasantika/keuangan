<?php

namespace App\Concerns;

use App\Enums\JenisTautan;
use App\Models\TautanBerkas;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait MemilikiTautanBerkas
{
    /** @return MorphMany<TautanBerkas, $this> */
    public function tautanBerkas(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik')->orderBy('created_at');
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautanJenis(JenisTautan ...$jenis): MorphMany
    {
        return $this->tautanBerkas()->whereIn('jenis', array_map(fn (JenisTautan $j): string => $j->value, $jenis));
    }
}
