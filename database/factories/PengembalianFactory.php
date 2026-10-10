<?php

namespace Database\Factories;

use App\Enums\StatusPengembalian;
use App\Models\Pengajuan;
use App\Models\Pengembalian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengembalian>
 */
class PengembalianFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pengajuan_id' => Pengajuan::factory(),
            'tanggal_setor' => '2027-06-12',
            'jumlah' => '50000.00',
            'nomor_bukti_setor' => fake()->bothify('NTPN-########'),
            'status' => StatusPengembalian::Diajukan,
            'dibuat_oleh' => User::factory(),
        ];
    }

    public function status(StatusPengembalian $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
