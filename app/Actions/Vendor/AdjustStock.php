<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * Sets the stock a vendor has on hand for a product or one of its variants.
 *
 * This is an absolute count, not a delta: the vendor is reporting what is on the
 * shelf after a delivery or a stock take. Checkout is what decrements stock, and it
 * does so under a row lock, so the two never race for the same number.
 */
final readonly class AdjustStock
{
    /**
     * @throws ValidationException
     */
    public function handle(Product|ProductVariant $stockable, int $quantity): Product|ProductVariant
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages([
                'stock_quantity' => __('Stock cannot be negative.'),
            ]);
        }

        $stockable->update(['stock_quantity' => $quantity]);

        return $stockable;
    }
}
