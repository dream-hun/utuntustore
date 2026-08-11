<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Suspends or reinstates an account.
 *
 * Suspension is account-level, not area-level: EnsureUserHasRole refuses a suspended
 * user everywhere and logs them out on their next request, so nothing else has to
 * remember to check. The record itself is left intact — the customer's order history
 * still belongs to the vendors who delivered those orders.
 */
final readonly class SetUserStatus
{
    public function handle(User $user, UserStatus $status): User
    {
        $user->status = $status;
        $user->save();

        return $user;
    }
}
