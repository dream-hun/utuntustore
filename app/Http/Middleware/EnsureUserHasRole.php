<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates an area of the application to one or more roles.
 *
 * A suspended user is refused everywhere, regardless of role — suspension is an
 * account-level state, so checking it here means no individual controller can
 * forget to.
 */
final class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        if ($user->status === UserStatus::Suspended) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, __('Your account has been suspended.'));
        }

        $permitted = array_map(
            UserRole::from(...),
            $roles,
        );

        abort_unless(in_array($user->role, $permitted, true), 403);

        return $next($request);
    }
}
