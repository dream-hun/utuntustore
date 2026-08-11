<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

final class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * A vendor manages their own shop profile; an admin can see any of them.
     */
    public function update(User $user, Vendor $vendor): bool
    {
        return $vendor->user_id === $user->id || $user->isAdmin();
    }

    /**
     * Approval, rejection and suspension are marketplace actions reserved for admins.
     *
     * Suspension is the platform's only real recourse in a dispute — since it never
     * held the customer's money, it has no refund to offer.
     */
    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Managing delivery coverage stays available to an expired vendor: coverage is
     * shop configuration, not an act of selling.
     */
    public function manageDelivery(User $user, Vendor $vendor): bool
    {
        return $vendor->user_id === $user->id;
    }
}
