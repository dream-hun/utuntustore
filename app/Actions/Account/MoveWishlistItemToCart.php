<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Cart\AddToCart;
use App\Exceptions\CheckoutException;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a saved product into the basket.
 *
 * A wishlist can sit untouched for months, so availability is re-checked by
 * AddToCart. If it throws, the transaction rolls back and the product stays on the
 * wishlist — losing a saved item because the shop happened to be out of stock would
 * be worse than the failure itself.
 */
final readonly class MoveWishlistItemToCart
{
    public function __construct(
        private AddToCart $addToCart,
        private RemoveFromWishlist $removeFromWishlist,
    ) {}

    /**
     * @throws CheckoutException
     */
    public function handle(User $user, Cart $cart, Product $product, int $quantity = 1): void
    {
        DB::transaction(function () use ($user, $cart, $product, $quantity): void {
            $this->addToCart->handle($cart, $product, $quantity);
            $this->removeFromWishlist->handle($user, $product);
        });
    }
}
