<?php

namespace App\Listeners;

use App\Enums\Peran;
use App\Events\IndikasiBuktiGandaTerdeteksi;
use App\Models\User;
use App\Notifications\IndikasiBuktiGanda;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class KirimNotifikasiBuktiGanda implements ShouldQueue
{
    public string $queue = 'notifikasi';

    public function handle(IndikasiBuktiGandaTerdeteksi $event): void
    {
        $penerima = User::query()->where('aktif', true)->role([Peran::AdminKeuangan->value, Peran::Bpp->value])->get();

        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new IndikasiBuktiGanda($event->realisasi));
        }
    }
}
