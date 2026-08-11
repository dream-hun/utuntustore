<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionPaymentMethod;
use App\Enums\VendorSubscriptionStatus;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorSubscription>
 */
final class VendorSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default is a subscription an admin has not confirmed payment for yet, so it
     * grants nothing. Amounts are whole RWF: the configured fee, copied at creation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory()->approved(),
            'amount' => 20000,
            'currency' => 'RWF',
            'status' => VendorSubscriptionStatus::Pending,
            'starts_at' => now(),
            'ends_at' => now()->addDays(365),
            'payment_method' => fake()->randomElement(SubscriptionPaymentMethod::cases()),
            'reference' => Str::upper(Str::random(10)),
            'paid_at' => null,
            'recorded_by' => null,
        ];
    }

    /**
     * Indicate that an admin recorded the payment and the year is running.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VendorSubscriptionStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addDays(365),
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the paid period ran out. The row stays as revenue history.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VendorSubscriptionStatus::Expired,
            'starts_at' => now()->subDays(400),
            'ends_at' => now()->subDays(35),
            'paid_at' => now()->subDays(400),
        ]);
    }

    /**
     * Indicate that the subscription was cancelled rather than deleted.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VendorSubscriptionStatus::Cancelled,
        ]);
    }
}
