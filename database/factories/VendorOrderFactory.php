<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorOrder>
 */
final class VendorOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Amounts are whole RWF and internally consistent:
     * total = subtotal - discount + shipping_fee + tax. The total is exactly what the
     * vendor collects in cash at the door — nothing is deducted or settled.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10, 3000) * 100;
        $discount = fake()->boolean(25) ? intdiv($subtotal, 10) : 0;
        $shippingFee = fake()->numberBetween(0, 10) * 500;
        $tax = 0;

        return [
            'order_id' => Order::factory(),
            'vendor_id' => Vendor::factory(),
            'order_number' => 'VO-'.Str::upper(Str::random(10)),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee' => $shippingFee,
            'tax' => $tax,
            'total' => $subtotal - $discount + $shippingFee + $tax,
            'status' => OrderStatus::Pending,
            'delivered_at' => null,
        ];
    }

    /**
     * Indicate that the vendor accepted the order.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the vendor reported handing the order over. This is what makes
     * its items reviewable and what sales reporting counts.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Delivered,
            'delivered_at' => now()->subDays(fake()->numberBetween(0, 10)),
        ]);
    }

    /**
     * Indicate that the vendor order was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
            'delivered_at' => null,
        ]);
    }
}
