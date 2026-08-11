<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
final class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A brand new vendor: applied for, not yet reviewed, and with no subscription,
     * which means it cannot sell yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shopName = Str::title(Cast::string(fake()->unique()->words(2, true))).' '.Cast::string(fake()->randomElement(['Shop', 'Store', 'Market', 'Boutique']));

        return [
            'user_id' => User::factory()->vendor(),
            'shop_name' => $shopName,
            'slug' => Str::slug($shopName).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'phone' => '+250'.Cast::string(fake()->randomElement(['78', '79', '72', '73'])).fake()->numerify('#######'),
            'email' => fake()->unique()->companyEmail(),
            'status' => VendorStatus::Pending,
            'is_platform_owned' => false,
            'subscription_status' => SubscriptionStatus::None,
            'subscription_ends_at' => null,
            'delivery_notes' => fake()->sentence(),
            'approved_at' => null,
        ];
    }

    /**
     * Indicate that an admin approved the shop. Approval alone does not permit selling:
     * a paid subscription is still required.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VendorStatus::Approved,
            'approved_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }

    /**
     * Indicate that the vendor may sell: approved with a live subscription.
     */
    public function sellable(): static
    {
        return $this->approved()->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Active,
            'subscription_ends_at' => now()->addDays(fake()->numberBetween(30, 365)),
        ]);
    }

    /**
     * Indicate that the subscription lapsed but is still inside the grace period,
     * so the vendor may still sell.
     */
    public function grace(): static
    {
        return $this->approved()->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Grace,
            'subscription_ends_at' => now()->subDays(fake()->numberBetween(1, 5)),
        ]);
    }

    /**
     * Indicate that the subscription lapsed past its grace period, which makes the
     * vendor ineligible to sell without touching their catalog.
     */
    public function expired(): static
    {
        return $this->approved()->state(fn (array $attributes): array => [
            'subscription_status' => SubscriptionStatus::Expired,
            'subscription_ends_at' => now()->subDays(fake()->numberBetween(8, 120)),
        ]);
    }

    /**
     * Indicate that the shop belongs to the platform itself, which exempts it from
     * the subscription.
     */
    public function platformOwned(): static
    {
        return $this->sellable()->state(fn (array $attributes): array => [
            'is_platform_owned' => true,
        ]);
    }

    /**
     * Indicate that the application was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VendorStatus::Rejected,
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that an admin suspended the shop.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VendorStatus::Suspended,
        ]);
    }
}
