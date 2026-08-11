<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponUsage>
 */
final class CouponUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The order is created for the same user who redeemed the coupon. The discount is
     * whole RWF, funded entirely by the vendor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'user_id' => User::factory(),
            'order_id' => fn (array $attributes): Factory => Order::factory()->state([
                'user_id' => $attributes['user_id'],
            ]),
            'discount_amount' => fake()->numberBetween(5, 200) * 100,
        ];
    }
}
