<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Cast;

/**
 * Derives a parent order's status from the vendor orders it was split into.
 *
 * A multi-vendor order has no status of its own: three shops deliver on three
 * different days, so the customer-facing status is the least-advanced vendor order
 * still outstanding. The order is only delivered once every vendor has delivered.
 */
final readonly class SyncOrderStatus
{
    public function handle(Order $order): Order
    {
        $statuses = $order->vendorOrders()
            ->pluck('status')
            ->map(static fn (mixed $status): OrderStatus => $status instanceof OrderStatus
                ? $status
                : OrderStatus::from(Cast::string($status)))
            ->values()
            ->all();

        if ($statuses === []) {
            return $order;
        }

        $order->status = $this->derive($statuses);
        $order->save();

        return $order;
    }

    /**
     * @param  array<int, OrderStatus>  $statuses
     */
    private function derive(array $statuses): OrderStatus
    {
        $active = array_values(array_filter(
            $statuses,
            static fn (OrderStatus $status): bool => $status !== OrderStatus::Cancelled,
        ));

        // Every vendor cancelled, so the whole order is cancelled.
        if ($active === []) {
            return OrderStatus::Cancelled;
        }

        // Ordered least- to most-advanced; the order sits at the least advanced
        // vendor order, because that is the part the customer is still waiting for.
        $progression = [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
        ];

        foreach ($progression as $status) {
            if (in_array($status, $active, true)) {
                return $status;
            }
        }

        // Unreachable: $active is non-empty and every non-cancelled status appears in
        // $progression, so the loop always returns. Kept because the return type
        // demands it, and ignored for coverage because no input can drive it.
        // @codeCoverageIgnoreStart
        return OrderStatus::Pending;
        // @codeCoverageIgnoreEnd
    }
}
