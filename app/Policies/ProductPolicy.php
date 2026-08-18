<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * A vendor may only ever touch their own products.
 *
 * Ownership and selling eligibility are separate concerns: this policy answers
 * "is it yours", and the vendor.can-sell middleware answers "may you publish it".
 * An expired vendor can still edit and unpublish their own catalog.
 *
 * An admin curates the whole catalog: they may add, edit and remove any shop's
 * products. Publishing is the exception — it puts inventory in front of a buyer, so it
 * stays with a shop that currently may sell.
 */
final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isVendor()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function view(User $user, Product $product): bool
    {
        if ($this->owns($user, $product)) {
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

    public function update(User $user, Product $product): bool
    {
        if ($this->owns($user, $product)) {
            return true;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        if ($this->owns($user, $product)) {
            return true;
        }

        return $user->isAdmin();
    }

    /**
     * Publishing is the one action that also requires an active subscription,
     * because it is what puts inventory in front of customers.
     */
    public function publish(User $user, Product $product): bool
    {
        return $this->owns($user, $product) && $product->vendor->canSell();
    }

    private function owns(User $user, Product $product): bool
    {
        return $user->vendor !== null && $user->vendor->id === $product->vendor_id;
    }
}
