<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The single post-login destination, routed to the right area by role.
 *
 * Auth flows (registration, email verification, two-factor) all redirect here, so
 * this is the one place that decides where a given kind of user belongs.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        if ($user->isAdmin()) {
            return to_route('admin.dashboard');
        }

        if ($user->isVendor()) {
            return to_route('vendor.dashboard');
        }

        return to_route('account.orders.index');
    }
}
