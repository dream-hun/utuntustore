<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Prices are whole RWF, in realistic marketplace amounts. A new product is a draft
     * until the vendor publishes it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(Cast::string(fake()->unique()->words(3, true)));
        $price = fake()->numberBetween(10, 5000) * 100;

        return [
            'vendor_id' => Vendor::factory(),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->sentence(),
            'sku' => Str::upper(Str::random(10)),
            'price' => $price,
            'compare_at_price' => null,
            'currency' => 'RWF',
            'stock_quantity' => fake()->numberBetween(1, 200),
            'low_stock_threshold' => 5,
            'weight' => fake()->numberBetween(100, 20000),
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the product is live in the catalog. Whether it is actually
     * purchasable also depends on the owning vendor's selling eligibility.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the vendor retired the product.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Archived,
        ]);
    }

    /**
     * Indicate that the product is published but sold out.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => 0,
        ]);
    }

    /**
     * Indicate that stock has fallen to the vendor's low stock threshold.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => 2,
            'low_stock_threshold' => 5,
        ]);
    }

    /**
     * Indicate that the product is discounted against a higher original price.
     */
    public function onSale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'compare_at_price' => Cast::int($attributes['price']) + fake()->numberBetween(5, 100) * 100,
        ]);
    }
}
