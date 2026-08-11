<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\OrderPaymentMethod;
use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The customer-facing half of a checkout. An order that spans several vendors is split
 * into one vendor order per vendor, and `shipping_fee` is the sum of theirs.
 *
 * The totals record what the customer agreed to hand over at the door; the platform
 * never holds the money.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $user_id
 * @property string $order_number
 * @property OrderStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $discount
 * @property int $shipping_fee
 * @property int $tax
 * @property int $total
 * @property OrderPaymentMethod $payment_method
 * @property int $shipping_address_id
 * @property int|null $billing_address_id
 * @property CarbonImmutable|null $placed_at
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Address $shippingAddress
 * @property-read Address|null $billingAddress
 * @property-read Collection<int, VendorOrder> $vendorOrders
 * @property-read Collection<int, OrderItem> $items
 */
final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Address, $this> */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    /** @return BelongsTo<Address, $this> */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    /** @return HasMany<VendorOrder, $this> */
    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => OrderPaymentMethod::class,
            'placed_at' => 'datetime',
        ];
    }
}
