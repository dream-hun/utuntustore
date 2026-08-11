<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;

final readonly class RemoveFromWishlist
{
    public function __construct(private ResolveWishlist $resolveWishlist) {}

    public function handle(User $user, Product $product): void
    {
        $wishlist = $this->resolveWishlist->handle($user);

        WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_id', $product->id)
            ->delete();
    }
}
