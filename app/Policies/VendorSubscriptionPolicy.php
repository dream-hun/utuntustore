<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VendorSubscription;

/**
 * Subscriptions are the platform's revenue ledger.
 *
 * A vendor may read their own payment history but may never create, amend or
 * cancel a subscription — payment happens off-platform and only an admin who
 * confirmed receipt of the money can record it.
 */
final class VendorSubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isVendor()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function view(User $user, VendorSubscription $subscription): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->vendor !== null && $user->vendor->id === $subscription->vendor_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Subscription rows are never hard-deleted, so cancellation is the only
     * way to end one and it stays an admin action.
     */
    public function cancel(User $user): bool
    {
        return $user->isAdmin();
    }
}
