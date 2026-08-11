<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;

/**
 * Resolves the authenticated user inside a Form Request.
 *
 * `user()` is nullable on every request, but the forms using this are only reachable
 * behind the `auth` middleware. Resolving it explicitly matters most for rules that
 * scope a check to the owner: `Rule::exists(...)->where('user_id', $this->user()?->id)`
 * quietly degrades to `where('user_id', null)` for a guest, which is the kind of
 * widened check that stops protecting anything without ever failing loudly.
 */
trait ResolvesAuthenticatedUser
{
    protected function authenticatedUser(): User
    {
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
