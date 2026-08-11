<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AddressType;
use App\Models\Address;
use App\Models\Sector;
use App\Models\User;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The sector is created first and the district read back off it, so the pair is
     * always consistent for delivery coverage matching.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => AddressType::Shipping,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+250'.Cast::string(fake()->randomElement(['78', '79', '72', '73'])).fake()->numerify('#######'),
            'country' => 'RW',
            'sector_id' => Sector::factory(),
            'district_id' => fn (array $attributes): int => Cast::int(Sector::query()->whereKey($attributes['sector_id'])->value('district_id')),
            'cell' => fake()->streetName(),
            'village' => fake()->streetName(),
            'address_line' => fake()->streetAddress(),
            'landmark' => 'Near '.fake()->company(),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that this is the address the customer is billed at.
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AddressType::Billing,
        ]);
    }

    /**
     * Indicate that this is the customer's default address.
     */
    public function isDefault(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
