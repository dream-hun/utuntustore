<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use App\Models\Wishlist;

/**
 * Finds the customer's wishlist, creating it on first use.
 *
 * A user has at most one wishlist (user_id is unique), and it is created lazily
 * rather than at registration so accounts that never save anything carry no row.
 */
final readonly class ResolveWishlist
{
    public function handle(User $user): Wishlist
    {
        return Wishlist::query()->firstOrCreate(['user_id' => $user->id]);
    }
}
