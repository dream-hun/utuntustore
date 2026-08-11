<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large', 'Red', 'Blue', 'Black', '500g', '1kg']),
            'sku' => Str::upper(Str::random(10)),
            'price' => fake()->numberBetween(10, 5000) * 100,
            'stock_quantity' => fake()->numberBetween(1, 100),
            'weight' => fake()->numberBetween(100, 20000),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the variant is no longer offered.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the variant is sold out.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => 0,
        ]);
    }
}
