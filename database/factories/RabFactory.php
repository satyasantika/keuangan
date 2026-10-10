<?php

namespace Database\Factories;

use App\Enums\StatusRab;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rab>
 */
class RabFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pengajuan_id' => Pengajuan::factory(),
            'nomor_revisi' => 0,
            'status' => StatusRab::Draf,
            'total' => '0.00',
            'dibuat_oleh' => User::factory(),
        ];
    }

    public function status(StatusRab $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
