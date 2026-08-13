<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;

/**
 * The shapes every storefront screen serializes a product, vendor or category into.
 *
 * Kept in one place so the catalog, a category page, a vendor's shop page and the
 * home page can never drift into showing subtly different cards.
 */
trait PresentsCatalog
{
    /**
     * @return array<string, mixed>
     */
    protected function productCard(Product $product): array
    {
        return [
            'id' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'compare_at_price' => $product->compare_at_price,
            'currency' => $product->currency,
            'primary_image_url' => $product->getFirstMediaUrl('images', 'thumb') ?: null,
            'in_stock' => $product->isInStock(),
            'vendor' => $this->vendorRef($product->vendor),
        ];
    }

    /**
     * The minimum a product card needs to attribute a product to a shop.
     *
     * @return array<string, mixed>
     */
    protected function vendorRef(Vendor $vendor): array
    {
        return [
            'id' => $vendor->uuid,
            'shop_name' => $vendor->shop_name,
            'slug' => $vendor->slug,
        ];
    }

    /**
     * A vendor with the artwork a shop card or shop header needs.
     *
     * Requires the vendor's media relation to be loaded.
     *
     * @return array<string, mixed>
     */
    protected function vendorCard(Vendor $vendor): array
    {
        return [
            ...$this->vendorRef($vendor),
            'logo_url' => $vendor->getFirstMediaUrl('logo', 'thumb') ?: null,
            'can_sell' => $vendor->canSell(),
            'description' => $vendor->description,
        ];
    }

    /**
     * The top-level categories the storefront header builds its nav row from.
     *
     * Every browsing screen shares the same header, so every one of them has to
     * supply these — a nav that appears on some pages and not others reads as a
     * bug rather than as a design.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function navCategories(): array
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->limit(12)
            ->get()
            ->map(fn (Category $category): array => $this->categoryLink($category))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function categoryLink(Category $category): array
    {
        return [
            'id' => $category->uuid,
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }
}
