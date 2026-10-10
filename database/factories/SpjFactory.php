<?php

namespace Database\Factories;

use App\Enums\StatusSpj;
use App\Models\Pengajuan;
use App\Models\Spj;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Spj>
 */
class SpjFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pengajuan_id' => Pengajuan::factory(),
            'status' => StatusSpj::Draf,
            'dibuat_oleh' => User::factory(),
        ];
    }

    public function status(StatusSpj $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
