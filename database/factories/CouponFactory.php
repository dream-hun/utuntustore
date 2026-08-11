<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
final class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A null vendor_id means a platform-wide coupon. Fixed values are whole RWF;
     * percentage values are a plain percentage between 1 and 50.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => null,
            'code' => Str::upper(Str::random(10)),
            'type' => CouponType::Percentage,
            'value' => fake()->numberBetween(1, 50),
            'minimum_order_amount' => 0,
            'maximum_discount' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the coupon takes a flat amount off, in whole RWF.
     */
    public function fixed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::Fixed,
            'value' => fake()->numberBetween(5, 200) * 100,
        ]);
    }

    /**
     * Indicate that the coupon belongs to one vendor rather than the platform.
     */
    public function forVendor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'vendor_id' => Vendor::factory()->sellable(),
        ]);
    }

    /**
     * Indicate that the coupon's window has closed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the coupon has not opened yet.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addDays(7),
            'expires_at' => now()->addDays(37),
        ]);
    }

    /**
     * Indicate that the coupon hit its usage limit.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'usage_limit' => 10,
            'used_count' => 10,
        ]);
    }

    /**
     * Indicate that the coupon was switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
