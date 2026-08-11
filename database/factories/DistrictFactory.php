<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\District;
use App\Models\Province;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<District>
 */
final class DistrictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Creates its own province so a district is always usable without the reference
     * data seeder having run.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'name' => Str::title(Cast::string(fake()->unique()->words(2, true))),
            'code' => Str::upper(fake()->unique()->lexify('??????')),
        ];
    }
}
