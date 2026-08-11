<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;

/**
 * Saves a product for later.
 *
 * Adding the same product twice is a no-op rather than an error — the customer's
 * intent is "keep this", and the table's unique constraint says the same thing.
 */
final readonly class AddToWishlist
{
    public function __construct(private ResolveWishlist $resolveWishlist) {}

    public function handle(User $user, Product $product): WishlistItem
    {
        $wishlist = $this->resolveWishlist->handle($user);

        return WishlistItem::query()->firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
        ]);
    }
}
