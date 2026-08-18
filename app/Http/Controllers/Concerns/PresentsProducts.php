<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Support\Cast;
use App\Support\MediaUrl;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The shape a catalog product is serialized into for the screens that manage it.
 *
 * The vendor's own catalog and the admin's platform-wide one show the same product
 * with the same numbers; only the surrounding columns differ, so the admin screen
 * spreads this and adds the shop the product belongs to.
 */
trait PresentsProducts
{
    /**
     * Expects `variants_count` to have been counted and `category` and `media` eager
     * loaded — every caller paginates, so leaving them to lazy-load is an N+1 per row.
     *
     * @return array<string, mixed>
     */
    protected function productRow(Product $product): array
    {
        return [
            'id' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => $product->price,
            'compare_at_price' => $product->compare_at_price,
            'currency' => $product->currency,
            'stock_quantity' => $product->stock_quantity,
            'low_stock_threshold' => $product->low_stock_threshold,
            'weight' => $product->weight,
            'status' => $product->status->value,
            'published_at' => $product->published_at,
            'is_low_stock' => $product->isLowStock(),
            'variants_count' => Cast::int($product->getAttribute('variants_count')),
            'category' => [
                'id' => $product->category->uuid,
                'name' => $product->category->name,
            ],
            'images' => $product->getMedia('images')
                ->map(fn (Media $media): array => [
                    'id' => $media->uuid,
                    'url' => MediaUrl::for($media),
                    'thumb_url' => MediaUrl::for($media, 'thumb'),
                ])
                ->all(),
        ];
    }
}
