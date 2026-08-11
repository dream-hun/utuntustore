<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Removes a product from a vendor's catalog.
 *
 * A product somebody has already bought is archived rather than deleted. Order items
 * snapshot the name and price they were sold at, so history would survive the delete,
 * but the customer's order would lose its link back to the product page and its
 * reviews would go with it. Archiving keeps both and hides the product just as well.
 */
final readonly class DeleteProduct
{
    /**
     * @return bool True when the product was archived instead of deleted.
     */
    public function handle(Product $product): bool
    {
        return DB::transaction(function () use ($product): bool {
            if ($product->orderItems()->exists()) {
                $product->update([
                    'status' => ProductStatus::Archived,
                    'published_at' => null,
                ]);

                return true;
            }

            $product->delete();

            return false;
        });
    }
}
