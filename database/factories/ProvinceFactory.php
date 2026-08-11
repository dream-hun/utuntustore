<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Province;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Province>
 */
final class ProvinceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Provinces are seeded reference data. The factory exists so a test never has to
     * depend on the reference seeder having run, and generates codes that cannot
     * collide with the official ones.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(Cast::string(fake()->unique()->words(2, true))),
            'code' => Str::upper(fake()->unique()->lexify('??????')),
        ];
    }
}
