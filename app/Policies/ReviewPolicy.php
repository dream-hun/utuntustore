<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;

/**
 * Reviews are tied to a delivered purchase, which is what makes them verifiable.
 */
final class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $user->isAdmin();
    }

    /**
     * A customer may review an order item they bought, once, after their vendor
     * order was marked delivered.
     *
     * `delivered` is a vendor-reported fact rather than a verified handover, but it
     * is the only signal the platform has that the customer actually received the
     * goods — and gating on it is what keeps reviews attached to real purchases.
     */
    public function createForOrderItem(User $user, OrderItem $orderItem): bool
    {
        if ($orderItem->order->user_id !== $user->id) {
            return false;
        }

        if ($orderItem->vendorOrder->delivered_at === null) {
            return false;
        }

        return ! $orderItem->review()->exists();
    }
}
