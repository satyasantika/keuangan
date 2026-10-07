<?php

arch('app/Enums hanya berisi enum')
    ->expect('App\Enums')
    ->toBeEnums();

arch('tidak ada fungsi debug di app')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('model tidak memakai facade DB secara langsung')
    ->expect('App\Models')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch()->preset()->php();

arch()->preset()->security();
