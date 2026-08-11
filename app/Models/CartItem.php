<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The unit_price is a display snapshot only. Checkout re-resolves it from the product
 * so a stale cart can never lock in an outdated price.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $unit_price
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Cart $cart
 * @property-read Product $product
 * @property-read ProductVariant|null $productVariant
 */
final class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
