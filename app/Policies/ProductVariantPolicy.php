<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

/**
 * A variant inherits its owner from its product.
 */
final class ProductVariantPolicy
{
    public function view(User $user, ProductVariant $variant): bool
    {
        if ($this->owns($user, $variant)) {
            return true;
        }

        return $user->isAdmin();
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $this->owns($user, $variant);
    }

    public function delete(User $user, ProductVariant $variant): bool
    {
        return $this->owns($user, $variant);
    }

    private function owns(User $user, ProductVariant $variant): bool
    {
        return $user->vendor !== null && $user->vendor->id === $variant->product->vendor_id;
    }
}
