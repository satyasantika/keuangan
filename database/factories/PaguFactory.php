<?php

namespace Database\Factories;

use App\Enums\StatusPagu;
use App\Models\Pagu;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagu>
 */
class PaguFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun_anggaran_id' => fn () => TahunAnggaran::query()->latest('tahun')->value('id'),
            'unit_id' => Unit::factory(),
            'jumlah' => '100000000.00',
            'dibuat_oleh' => User::factory(),
        ];
    }

    public function ditetapkan(): static
    {
        return $this->afterCreating(function (Pagu $pagu): void {
            $pagu->forceFill(['status' => StatusPagu::Ditetapkan, 'ditetapkan_pada' => now()])->save();
        });
    }
}
