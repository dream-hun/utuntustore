<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\ProductStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Prices are whole RWF integers; RWF has no minor unit in practice, so integer
 * arithmetic avoids rounding when an order is split across vendors.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $vendor_id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $short_description
 * @property string|null $sku
 * @property int $price
 * @property int|null $compare_at_price
 * @property string $currency
 * @property int $stock_quantity
 * @property int $low_stock_threshold
 * @property int|null $weight
 * @property ProductStatus $status
 * @property CarbonImmutable|null $published_at
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Vendor $vendor
 * @property-read Category $category
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, Review> $reviews
 * @property-read Collection<int, OrderItem> $orderItems
 */
final class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUuid;
    use InteractsWithMedia;

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    /**
     * Products carry a gallery rather than a single file, so this collection is
     * deliberately not `singleFile()`.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->width(400)
            ->height(400);

        $this->addMediaConversion('web')
            ->width(1200);
    }

    /**
     * Constrain a query to products the vendor has actually published.
     *
     * A future published_at is a scheduled release and stays hidden until it arrives.
     *
     * @param  Builder<$this>  $query
     */
    protected function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * The storefront visibility rule: a product is only sellable when it is published
     * AND its vendor is still eligible to sell.
     *
     * Publication alone is not enough — a vendor whose subscription lapsed keeps their
     * published catalog in their own dashboard, but it must disappear from the
     * storefront. Every customer-facing product query should start from this scope.
     *
     * @param  Builder<$this>  $query
     */
    protected function scopeSellable(Builder $query): void
    {
        $query->published()->whereHas('vendor', $this->sellableVendor(...));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * The vendor half of {@see scopeSellable}, kept as a named method so the builder
     * it constrains is known to be a Vendor one and `sellable()` resolves against it.
     *
     * @param  Builder<Vendor>  $query
     */
    private function sellableVendor(Builder $query): void
    {
        $query->sellable();
    }
}
