<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Marketplace isolation is enforced by policies, so every controller needs to be
     * able to authorize. Vendors and customers share routes' shapes but never data.
     */
    use AuthorizesRequests;

    /**
     * The authenticated user behind the current request.
     *
     * `$request->user()` is nullable because a request need not be authenticated, but
     * every route reaching these controllers sits behind the `auth` middleware. A null
     * here therefore means a route was registered without it — a misconfiguration worth
     * failing on directly, rather than passing null into an action typed against User
     * and getting a confusing error further in.
     */
    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
