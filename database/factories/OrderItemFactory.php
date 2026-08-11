<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\VendorOrder;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The vendor order is created against the same order, and the product name, SKU
     * and price are snapshotted, so editing or deleting a product can never rewrite
     * what the customer agreed to buy.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_order_id' => fn (array $attributes): Factory => VendorOrder::factory()->state([
                'order_id' => $attributes['order_id'],
            ]),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'product_name' => fn (array $attributes): string => Cast::string(
                Product::query()->whereKey($attributes['product_id'])->value('name'),
                Str::title(Cast::string(fake()->words(3, true))),
            ),
            'variant_name' => null,
            'sku' => function (array $attributes): ?string {
                $sku = Product::query()->whereKey($attributes['product_id'])->value('sku');

                return $sku === null ? null : Cast::string($sku);
            },
            'unit_price' => fn (array $attributes): int => Cast::int(
                Product::query()->whereKey($attributes['product_id'])->value('price'),
                fake()->numberBetween(10, 5000) * 100,
            ),
            'quantity' => fake()->numberBetween(1, 5),
            'subtotal' => fn (array $attributes): int => Cast::int($attributes['unit_price']) * Cast::int($attributes['quantity']),
        ];
    }
}
