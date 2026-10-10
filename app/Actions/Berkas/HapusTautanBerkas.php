<?php

namespace App\Actions\Berkas;

use App\Contracts\PenyimpananBerkas;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class HapusTautanBerkas
{
    public function __construct(private readonly PenyimpananBerkas $penyimpanan) {}

    public function handle(TautanBerkas $tautan, User $oleh): void
    {
        if (! $oleh->can('delete', $tautan)) {
            throw new AuthorizationException('Anda tidak berhak menghapus tautan ini.');
        }

        $this->penyimpanan->hapus($tautan, $oleh);
    }
}
