<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\District;
use App\Models\Sector;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorDeliveryArea>
 */
final class VendorDeliveryAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The sector is created first and the district is read back off it, because a
     * sector that does not belong to the district on the same row silently breaks
     * coverage matching.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'sector_id' => Sector::factory(),
            'district_id' => fn (array $attributes): int => $attributes['sector_id'] === null
                ? District::factory()->create()->id
                : Cast::int(Sector::query()->whereKey($attributes['sector_id'])->value('district_id')),
            'delivery_fee' => fake()->numberBetween(0, 10) * 500,
            'estimated_days_min' => 1,
            'estimated_days_max' => fake()->numberBetween(2, 5),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the vendor covers the whole district rather than one sector.
     */
    public function districtWide(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sector_id' => null,
        ]);
    }

    /**
     * Indicate that the vendor delivers here for free.
     */
    public function freeDelivery(): static
    {
        return $this->state(fn (array $attributes): array => [
            'delivery_fee' => 0,
        ]);
    }

    /**
     * Indicate that the vendor stopped covering this area.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
