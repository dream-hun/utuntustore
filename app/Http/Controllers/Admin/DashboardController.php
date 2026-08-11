<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CalculatePlatformRevenue;
use App\Actions\Admin\CompilePlatformMetrics;
use App\Actions\Admin\ListRecentSignups;
use App\Actions\Admin\ListUpcomingExpiries;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform health at a glance.
 *
 * Every figure here is an aggregate over the whole marketplace, so all of them are
 * deferred: the shell renders immediately and each card fills in as its query lands,
 * which matters on the slow connections this market runs on.
 */
final class DashboardController extends Controller
{
    public function __invoke(
        CompilePlatformMetrics $metrics,
        CalculatePlatformRevenue $revenue,
        ListUpcomingExpiries $expiries,
        ListRecentSignups $signups,
    ): Response {
        return Inertia::render('admin/dashboard', [
            'metrics' => Inertia::defer(fn (): array => $metrics->handle()),

            // Revenue is subscriptions, never orders. Order totals are gross
            // merchandise value the platform takes no share of.
            'revenue' => Inertia::defer(fn (): array => [
                'all_time' => $revenue->handle(),
                'this_month' => $revenue->handle(now()->startOfMonth(), now()),
                'this_year' => $revenue->handle(now()->startOfYear(), now()),
            ], 'revenue'),

            'upcomingExpiries' => Inertia::defer(
                fn (): array => $expiries->handle(withinDays: 30, limit: 8),
                'activity',
            ),

            'recentSignups' => Inertia::defer(
                fn (): array => $signups->handle(limit: 5),
                'activity',
            ),
        ]);
    }
}
