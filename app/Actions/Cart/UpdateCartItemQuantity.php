<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Exceptions\CheckoutException;
use App\Models\CartItem;
use App\Support\Checkout\CheckoutProblem;
use Illuminate\Support\Facades\Config;

/**
 * Sets an explicit quantity on a cart line, removing it when the quantity reaches zero.
 */
final readonly class UpdateCartItemQuantity
{
    /**
     * @throws CheckoutException
     */
    public function handle(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $quantity = min($quantity, Config::integer('marketplace.catalog.max_cart_quantity'));

        $available = $item->productVariant->stock_quantity ?? $item->product->stock_quantity;

        if ($available < $quantity) {
            throw new CheckoutException(
                CheckoutProblem::InsufficientStock->message(),
                [CheckoutProblem::InsufficientStock],
            );
        }

        $item->quantity = $quantity;
        $item->save();

        return $item;
    }
}
