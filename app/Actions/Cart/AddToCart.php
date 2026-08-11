<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Exceptions\CheckoutException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Checkout\CheckoutProblem;
use Illuminate\Support\Facades\Config;

/**
 * Adds a product to a cart, or increases the quantity if it is already there.
 *
 * Availability is checked here for a good error message, but this is not the
 * authoritative gate — checkout re-validates everything, because a cart can sit open
 * for days while stock, prices and vendor subscriptions all change underneath it.
 */
final readonly class AddToCart
{
    /**
     * @throws CheckoutException
     */
    public function handle(Cart $cart, Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        if (! $product->vendor->canSell()) {
            throw new CheckoutException(
                CheckoutProblem::VendorCannotSell->message(),
                [CheckoutProblem::VendorCannotSell],
            );
        }

        $available = $variant->stock_quantity ?? $product->stock_quantity;

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        $desired = ($item->quantity ?? 0) + $quantity;
        $desired = min($desired, Config::integer('marketplace.catalog.max_cart_quantity'));

        if ($available < $desired) {
            throw new CheckoutException(
                CheckoutProblem::InsufficientStock->message(),
                [CheckoutProblem::InsufficientStock],
            );
        }

        if ($item instanceof CartItem) {
            $item->quantity = $desired;
            $item->unit_price = $variant->price ?? $product->price;
            $item->save();

            return $item;
        }

        $item = new CartItem;
        $item->cart_id = $cart->id;
        $item->product_id = $product->id;
        $item->product_variant_id = $variant?->id;
        $item->quantity = $desired;
        $item->unit_price = $variant->price ?? $product->price;
        $item->save();

        return $item;
    }
}
