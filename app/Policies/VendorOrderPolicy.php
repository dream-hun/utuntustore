<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VendorOrder;

/**
 * The isolation boundary of the marketplace.
 *
 * A vendor sees a customer's delivery details only through their own vendor order,
 * never through the parent order — which is why there is no vendor-facing route to
 * an Order and why this policy, not OrderPolicy, guards the vendor's view of a sale.
 *
 * An expired vendor keeps full access here. They still have to deliver orders they
 * already accepted, and the platform holds nothing to withhold from them.
 */
final class VendorOrderPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isVendor()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function view(User $user, VendorOrder $vendorOrder): bool
    {
        if ($this->owns($user, $vendorOrder)) {
            return true;
        }

        return $user->isAdmin();
    }

    /**
     * Only the owning vendor may move a vendor order through its lifecycle.
     * Delivery is a vendor-reported fact, so nobody else can report it.
     */
    public function update(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->owns($user, $vendorOrder);
    }

    private function owns(User $user, VendorOrder $vendorOrder): bool
    {
        return $user->vendor !== null && $user->vendor->id === $vendorOrder->vendor_id;
    }
}
