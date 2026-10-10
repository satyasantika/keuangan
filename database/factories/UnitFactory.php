<?php

namespace Database\Factories;

use App\Enums\JenisUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->bothify('U??##')),
            'nama' => 'Program Studi '.fake()->words(2, true),
            'jenis' => JenisUnit::Prodi,
            'jenjang' => 'S1',
            'aktif' => true,
        ];
    }
}
