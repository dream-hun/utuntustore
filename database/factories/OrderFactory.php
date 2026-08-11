<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderPaymentMethod;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Every amount is whole RWF and the totals are internally consistent:
     * total = subtotal - discount + shipping_fee + tax.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10, 5000) * 100;
        $discount = fake()->boolean(25) ? intdiv($subtotal, 10) : 0;
        $shippingFee = fake()->numberBetween(0, 10) * 500;
        $tax = 0;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.Str::upper(Str::random(10)),
            'status' => OrderStatus::Pending,
            'currency' => 'RWF',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee' => $shippingFee,
            'tax' => $tax,
            'total' => $subtotal - $discount + $shippingFee + $tax,
            'payment_method' => OrderPaymentMethod::CashOnDelivery,
            'shipping_address_id' => fn (array $attributes): Factory => Address::factory()->state([
                'user_id' => $attributes['user_id'],
            ]),
            'billing_address_id' => null,
            'placed_at' => now(),
        ];
    }

    /**
     * Indicate that the customer confirmed the order.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that every vendor handed the order over and collected their cash.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Delivered,
            'placed_at' => now()->subDays(fake()->numberBetween(2, 14)),
        ]);
    }

    /**
     * Indicate that the order was cancelled, which releases its stock.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
