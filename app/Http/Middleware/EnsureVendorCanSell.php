<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards actions that would put new inventory in front of customers.
 *
 * Ownership and selling eligibility are deliberately separate checks. An expired
 * vendor keeps full access to their dashboard, orders, sales history and existing
 * catalog — they simply cannot publish or sell until they renew. This middleware
 * belongs only on the routes that create or publish, never on the whole area.
 */
final class EnsureVendorCanSell
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = resolve(Vendor::class);

        abort_unless($vendor->canSell(), 403, __('Your subscription is not active. Renew it to continue selling.'));

        return $next($request);
    }
}
