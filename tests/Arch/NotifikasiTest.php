<?php

use Illuminate\Contracts\Queue\ShouldQueue;

arch('semua notifikasi diantrekan')
    ->expect('App\Notifications')
    ->classes()
    ->toImplement(ShouldQueue::class)
    ->ignoring('App\Notifications\Channels');

arch('listener notifikasi diantrekan')
    ->expect('App\Listeners')
    ->classes()
    ->toImplement(ShouldQueue::class);
