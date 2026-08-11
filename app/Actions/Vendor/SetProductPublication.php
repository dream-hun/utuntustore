<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Puts a product in front of customers, or takes it back off the shelf.
 *
 * Publishing is the only catalog action gated on selling eligibility, and the caller
 * is expected to have gone through the `vendor.can-sell` middleware. The check is
 * repeated here because this Action is also reachable from jobs and commands, where
 * no middleware ran.
 *
 * Unpublishing is never gated: a vendor must always be able to pull a product they
 * can no longer supply, whatever their subscription says.
 */
final readonly class SetProductPublication
{
    /**
     * @throws ValidationException
     */
    public function handle(Product $product, bool $published): Product
    {
        if (! $published) {
            $product->update([
                'status' => ProductStatus::Draft,
                'published_at' => null,
            ]);

            return $product;
        }

        if (! $product->vendor->canSell()) {
            throw ValidationException::withMessages([
                'status' => __('Your subscription is not active, so you cannot publish products.'),
            ]);
        }

        $product->update([
            'status' => ProductStatus::Published,
            'published_at' => $product->published_at ?? now(),
        ]);

        return $product;
    }
}
