<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The order item is created against the same product, because a review is tied to
     * a specific purchase — that is what makes it verifiable.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'order_item_id' => fn (array $attributes): Factory => OrderItem::factory()->state([
                'product_id' => $attributes['product_id'],
            ]),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'comment' => fake()->paragraph(),
            'status' => ReviewStatus::Pending,
        ];
    }

    /**
     * Indicate that a moderator published the review.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Approved,
        ]);
    }

    /**
     * Indicate that a moderator rejected the review.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Rejected,
        ]);
    }
}
