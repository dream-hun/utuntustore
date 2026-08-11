<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Edits a product a vendor already owns.
 *
 * This deliberately stays available to a vendor whose subscription has lapsed. Their
 * catalog is their own data and is already hidden from the storefront by
 * Product::sellable(); locking them out of it would only make renewing harder.
 *
 * The slug is never rewritten — it is the product's public URL and may already be
 * shared, bookmarked or indexed.
 */
final readonly class UpdateProduct
{
    /**
     * @param  array{name: string, category_id: string, description: string|null, short_description: string|null, sku: string|null, price: int, compare_at_price: int|null, stock_quantity: int, low_stock_threshold: int, weight: int|null}  $attributes
     * @param  array<int, UploadedFile>  $images  Appended to the gallery, never replacing it.
     */
    public function handle(Product $product, array $attributes, array $images = []): Product
    {
        return DB::transaction(function () use ($product, $attributes, $images): Product {
            $category = Category::query()->where('uuid', $attributes['category_id'])->firstOrFail();

            $product->update([
                'category_id' => $category->id,
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'short_description' => $attributes['short_description'],
                'sku' => $attributes['sku'],
                'price' => $attributes['price'],
                'compare_at_price' => $attributes['compare_at_price'],
                'stock_quantity' => $attributes['stock_quantity'],
                'low_stock_threshold' => $attributes['low_stock_threshold'],
                'weight' => $attributes['weight'],
            ]);

            foreach ($images as $image) {
                $product->addMedia($image)->toMediaCollection('images');
            }

            return $product->refresh();
        });
    }
}
