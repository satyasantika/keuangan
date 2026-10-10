<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Penjadwal (zona Asia/Jakarta).
*/
Schedule::command('keuangan:pengingat-spj')->dailyAt('07:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
Schedule::command('keuangan:rekap-mingguan')->weeklyOn(1, '07:30')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
Schedule::command('keuangan:pengingat-kunci-periode')->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
Schedule::command('keuangan:rekonsiliasi')->dailyAt('01:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
Schedule::command('keuangan:periksa-ulang-tautan')->weeklyOn(0, '03:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
Schedule::command('keuangan:bersihkan-sementara')->hourlyAt(15)->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
