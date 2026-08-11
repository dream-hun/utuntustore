<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the signed-in user's vendor once per request and binds it to the container.
 *
 * Every vendor-area controller needs it, and having a single resolution point means
 * a controller can type-hint Vendor and be certain it belongs to the current user —
 * there is no code path where an arbitrary vendor could be injected instead.
 */
final class ResolveCurrentVendor
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user()?->vendor;

        abort_unless($vendor instanceof Vendor, 403, __('You do not have a vendor account.'));

        app()->instance(Vendor::class, $vendor);

        return $next($request);
    }
}
