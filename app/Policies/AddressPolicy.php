<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

/**
 * Addresses are private to the customer who owns them.
 *
 * A vendor never reaches an Address through this policy — they see the delivery
 * details of a customer who ordered from them only as a snapshot on their own
 * vendor order.
 */
final class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function update(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function delete(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }
}
