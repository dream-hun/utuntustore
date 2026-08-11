<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

/**
 * Vendors own their own coupons.
 *
 * Because the platform takes no commission, every discount is funded entirely by
 * the vendor offering it — so a vendor may create and withdraw their own, and only
 * an admin may touch a platform-wide coupon (vendor_id null).
 */
final class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isVendor()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function view(User $user, Coupon $coupon): bool
    {
        if ($this->owns($user, $coupon)) {
            return true;
        }

        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        if ($user->isVendor()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function update(User $user, Coupon $coupon): bool
    {
        if ($this->owns($user, $coupon)) {
            return true;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        if ($this->owns($user, $coupon)) {
            return true;
        }

        return $user->isAdmin();
    }

    private function owns(User $user, Coupon $coupon): bool
    {
        return $coupon->vendor_id !== null
            && $user->vendor !== null
            && $user->vendor->id === $coupon->vendor_id;
    }
}
