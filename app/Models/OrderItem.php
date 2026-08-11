<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A record of what the customer agreed to buy, not a pointer to a live product.
 *
 * The name, variant name, SKU and unit price are snapshotted, and product_id is
 * nullable, so editing or deleting a product can never rewrite order history.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $order_id
 * @property int $vendor_order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string $product_name
 * @property string|null $variant_name
 * @property string|null $sku
 * @property int $unit_price
 * @property int $quantity
 * @property int $subtotal
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read VendorOrder $vendorOrder
 * @property-read Product|null $product
 * @property-read ProductVariant|null $productVariant
 * @property-read Review|null $review
 */
final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<VendorOrder, $this> */
    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * At most one review per purchased item, which is what makes a review verifiable.
     *
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
