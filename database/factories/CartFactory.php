<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
final class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => null,
            'currency' => 'RWF',
        ];
    }

    /**
     * Indicate that the cart belongs to a guest, keyed by session instead of user.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'session_id' => Str::random(40),
        ]);
    }
}
