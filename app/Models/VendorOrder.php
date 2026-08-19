<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VendorOrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * A vendor's slice of a customer order, and the row that makes marketplace isolation
 * possible: a vendor may only ever query rows whose vendor_id is their own.
 *
 * `total` is the cash the vendor collects at the door, so there is nothing to settle.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $order_id
 * @property int $vendor_id
 * @property string $order_number
 * @property int $subtotal
 * @property int $discount
 * @property int $shipping_fee
 * @property int $tax
 * @property int $total
 * @property OrderStatus $status
 * @property CarbonImmutable|null $delivered_at
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read Vendor $vendor
 * @property-read Collection<int, OrderItem> $items
 */
final class VendorOrder extends Model
{
    /** @use HasFactory<VendorOrderFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivered_at' => 'datetime',
        ];
    }
}
