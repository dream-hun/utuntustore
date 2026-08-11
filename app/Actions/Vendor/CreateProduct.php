<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a product to a vendor's own catalog, as a draft.
 *
 * Nothing created here is visible to customers: publishing is a separate, guarded
 * action, because that is the step that puts inventory in front of a buyer and so
 * the step that requires a live subscription.
 *
 * Every money value is a whole number of RWF.
 */
final readonly class CreateProduct
{
    /**
     * @param  array{name: string, category_id: string, description: string|null, short_description: string|null, sku: string|null, price: int, compare_at_price: int|null, stock_quantity: int, low_stock_threshold: int, weight: int|null}  $attributes
     * @param  array<int, UploadedFile>  $images
     */
    public function handle(Vendor $vendor, array $attributes, array $images = []): Product
    {
        return DB::transaction(function () use ($vendor, $attributes, $images): Product {
            $category = Category::query()->where('uuid', $attributes['category_id'])->firstOrFail();

            $product = Product::query()->create([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'name' => $attributes['name'],
                // Suffixed because product names repeat across shops and the slug is unique
                // marketplace-wide.
                'slug' => Str::slug($attributes['name']).'-'.Str::lower(Str::random(6)),
                'description' => $attributes['description'],
                'short_description' => $attributes['short_description'],
                'sku' => $attributes['sku'],
                'price' => $attributes['price'],
                'compare_at_price' => $attributes['compare_at_price'],
                'currency' => Config::string('marketplace.currency'),
                'stock_quantity' => $attributes['stock_quantity'],
                'low_stock_threshold' => $attributes['low_stock_threshold'],
                'weight' => $attributes['weight'],
                'status' => ProductStatus::Draft,
                'published_at' => null,
            ]);

            foreach ($images as $image) {
                $product->addMedia($image)->toMediaCollection('images');
            }

            return $product;
        });
    }
}
