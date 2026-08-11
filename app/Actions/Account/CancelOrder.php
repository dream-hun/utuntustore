<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Orders\TransitionVendorOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\VendorOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a customer order by cancelling each of its vendor orders.
 *
 * The parent order has no lifecycle of its own — it is derived from its vendor
 * orders — so cancellation is delegated to TransitionVendorOrder, which is the one
 * place that validates the transition, restores the committed stock and re-derives
 * the parent status.
 *
 * A vendor already on the road is left alone: on a multi-vendor order the customer
 * cancels the parts that have not shipped, and the shipped part still arrives.
 */
final readonly class CancelOrder
{
    public function __construct(private TransitionVendorOrder $transitionVendorOrder) {}

    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $cancellable = $order->vendorOrders()
                ->get()
                ->filter(static fn (VendorOrder $vendorOrder): bool => $vendorOrder->status->canTransitionTo(OrderStatus::Cancelled));

            if ($cancellable->isEmpty()) {
                throw new DomainException(__('This order can no longer be cancelled.'));
            }

            foreach ($cancellable as $vendorOrder) {
                $this->transitionVendorOrder->handle($vendorOrder, OrderStatus::Cancelled);
            }

            return $order->refresh();
        });
    }
}
