<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendor\BuildVendorSalesReport;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A record of cash the vendor already collected at the door.
 *
 * This is not a balance, a payout or an amount owed, and it must never be labelled as
 * one: the customer handed the money straight to the vendor and the platform was never
 * a party to it. The only figure that flows the other way is the vendor's own
 * subscription, shown here as the business expense it is.
 */
final class SalesController extends Controller
{
    /**
     * The reporting windows offered, resolved server-side so a hand-edited query
     * string cannot ask for an unbounded scan.
     */
    private const array PERIODS = ['this_month', 'last_month', 'last_30_days', 'this_year', 'all_time'];

    public function __invoke(Request $request, Vendor $vendor, BuildVendorSalesReport $report): Response
    {
        $period = (string) $request->query('period', 'this_month');

        if (! in_array($period, self::PERIODS, true)) {
            $period = 'this_month';
        }

        [$from, $to] = $this->window($period, $vendor);

        return Inertia::render('vendor/sales', [
            'period' => $period,
            'range' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'report' => Inertia::defer(fn (): array => $report->handle($vendor, $from, $to)),
        ]);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function window(string $period, Vendor $vendor): array
    {
        $now = now();

        return match ($period) {
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_30_days' => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'all_time' => [$vendor->created_at?->copy()->startOfDay() ?? $now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }
}
