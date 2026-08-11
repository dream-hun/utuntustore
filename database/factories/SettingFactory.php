<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Setting>
 */
final class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::snake(Cast::string(fake()->unique()->words(3, true))),
            'value' => (string) fake()->numberBetween(1, 100000),
        ];
    }

    /**
     * The annual subscription fee in whole RWF.
     */
    public function subscriptionFee(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'vendor_subscription_fee',
            'value' => '20000',
        ]);
    }

    /**
     * The currency subscriptions are charged in.
     */
    public function subscriptionCurrency(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'vendor_subscription_currency',
            'value' => 'RWF',
        ]);
    }

    /**
     * How long a paid subscription period lasts.
     */
    public function subscriptionDays(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'vendor_subscription_days',
            'value' => '365',
        ]);
    }

    /**
     * How long a lapsed vendor may keep selling before expiring.
     */
    public function subscriptionGraceDays(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'vendor_subscription_grace_days',
            'value' => '7',
        ]);
    }
}
