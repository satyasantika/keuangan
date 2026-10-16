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

// Profil Ringan (STANDAR-TEKNIS §1b): tanpa Horizon/Redis. Penjadwal menyalakan worker tiap menit dan
// berhenti saat antrean kosong; jeda proses job paling lama ±1 menit.
// --memory=256 pada worker default melindungi RAM server dari job ringan yang membengkak.
Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=55 --timeout=60 --tries=3 --memory=256')
    ->everyMinute()->withoutOverlapping(2)->runInBackground();

Schedule::command('queue:work database-berat --queue=berat --stop-when-empty --max-time=600 --timeout=900 --tries=2 --memory=512')
    ->everyMinute()->withoutOverlapping(30)->runInBackground();

// Detak penjadwal: dibaca /api/health; peringatan bila lebih tua dari 5 menit.
Schedule::call(fn () => cache()->put(config('app.kode').':detak-penjadwal', now()->getTimestamp(), 600))
    ->everyMinute()->name('detak-penjadwal');

Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:00');
Schedule::command('queue:prune-batches --hours=168')->dailyAt('03:05');
