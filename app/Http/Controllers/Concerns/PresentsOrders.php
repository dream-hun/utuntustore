<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\VendorOrder;

/**
 * The shapes an order is serialized into, shared by the four areas that show one.
 *
 * A customer, a vendor and an admin all look at the same sale from different sides:
 * the customer sees the whole basket, the vendor sees only their own slice, the admin
 * sees everything and can change nothing. What they must never see is *different
 * numbers*, which is what four hand-written copies of these arrays eventually produce.
 *
 * Each area still adds its own fields on top by spreading, so nothing is forced to
 * ship data a screen has no business showing.
 */
trait PresentsOrders
{
    use PresentsAddresses;

    /**
     * One line of an order, snapshotted at the moment it was placed.
     *
     * @return array<string, mixed>
     */
    protected function orderItemLine(OrderItem $item): array
    {
        return [
            'id' => $item->uuid,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'unit_price' => $item->unit_price,
            'quantity' => $item->quantity,
            'subtotal' => $item->subtotal,
        ];
    }

    /**
     * The money on a vendor order — exactly the cash that shop collects at the door.
     *
     * @return array<string, mixed>
     */
    protected function vendorOrderTotals(VendorOrder $vendorOrder): array
    {
        return [
            'subtotal' => $vendorOrder->subtotal,
            'discount' => $vendorOrder->discount,
            'shipping_fee' => $vendorOrder->shipping_fee,
            'total' => $vendorOrder->total,
        ];
    }

    /**
     * Where this vendor order currently is, and what it may become.
     *
     * @return array<string, mixed>
     */
    protected function vendorOrderStatus(VendorOrder $vendorOrder): array
    {
        return [
            'status' => $vendorOrder->status->value,
            'allowed_transitions' => $this->statusOptions($vendorOrder->status->allowedTransitions()),
        ];
    }

    /**
     * @param  array<int, OrderStatus>|null  $statuses  Defaults to every status.
     * @return array<int, array{value: string, label: string}>
     */
    protected function statusOptions(?array $statuses = null): array
    {
        return array_map(
            static fn (OrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            $statuses ?? OrderStatus::cases(),
        );
    }
}
