<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\District;
use App\Models\Sector;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sector>
 */
final class SectorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Creates its own district (and therefore province) so a sector is always usable
     * without the reference data seeder having run.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'name' => Str::title(Cast::string(fake()->unique()->words(2, true))),
            'code' => Str::upper(fake()->unique()->lexify('??????')),
        ];
    }
}
