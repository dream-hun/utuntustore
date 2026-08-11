<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * An order belongs to the customer who placed it.
 *
 * Vendors are deliberately absent from this policy. A vendor's view of a sale is
 * their VendorOrder, which exposes only their own items and nothing about the
 * other vendors on the same order.
 */
final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->isAdmin();
    }

    /**
     * A customer may cancel only while nothing has shipped. Once a vendor is on the
     * road with the goods, cancellation is a conversation, not a button.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            return false;
        }

        return $order->status->canTransitionTo(OrderStatus::Cancelled);
    }
}
