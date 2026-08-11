<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\VendorOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Moves a vendor order through the cash-on-delivery lifecycle.
 *
 * `delivered` is a vendor-reported fact. The platform records the vendor's claim that
 * the handover happened; it never verifies that cash changed hands and does not need
 * to, since it has no financial exposure to the answer.
 */
final readonly class TransitionVendorOrder
{
    public function __construct(private SyncOrderStatus $syncOrderStatus) {}

    public function handle(VendorOrder $vendorOrder, OrderStatus $status): VendorOrder
    {
        if (! $vendorOrder->status->canTransitionTo($status)) {
            throw new DomainException(__('A :from order cannot become :to.', [
                'from' => $vendorOrder->status->label(),
                'to' => $status->label(),
            ]));
        }

        return DB::transaction(function () use ($vendorOrder, $status): VendorOrder {
            $vendorOrder->status = $status;

            if ($status === OrderStatus::Delivered) {
                $vendorOrder->delivered_at = now();
            }

            if ($status === OrderStatus::Cancelled) {
                $this->restoreStock($vendorOrder);
            }

            $vendorOrder->save();

            $this->syncOrderStatus->handle($vendorOrder->order);

            return $vendorOrder;
        });
    }

    /**
     * Return committed stock to the catalog when a vendor order is cancelled.
     *
     * Stock is deducted at checkout, so a cancellation that did not restore it would
     * silently shrink the vendor's inventory with every abandoned order.
     */
    private function restoreStock(VendorOrder $vendorOrder): void
    {
        foreach ($vendorOrder->items()->get() as $item) {
            if ($item->product_variant_id !== null) {
                $item->productVariant?->increment('stock_quantity', $item->quantity);

                continue;
            }

            $item->product?->increment('stock_quantity', $item->quantity);
        }
    }
}
