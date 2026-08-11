<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CalculatePlatformRevenue;
use App\Actions\Admin\CancelSubscription;
use App\Actions\Admin\ListUpcomingExpiries;
use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionPaymentRequest;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The revenue screen.
 *
 * Subscriptions are the platform's only income and this ledger is the whole of it.
 * Payment happens off-platform — mobile money, bank transfer or cash — and an admin
 * who confirmed receiving it records it here; the system never touches the money.
 *
 * Rows are never hard-deleted. A period that must stop is cancelled, so the revenue
 * history stays intact (BR-34).
 */
final class SubscriptionController extends Controller
{
    public function index(
        Request $request,
        CalculatePlatformRevenue $revenue,
        ListUpcomingExpiries $expiries,
        Settings $settings,
    ): Response {
        $this->authorize('viewAny', VendorSubscription::class);

        $status = (string) $request->query('status', '');
        $statusFilter = VendorSubscriptionStatus::tryFrom($status);
        [$from, $to, $period] = $this->period($request);

        $subscriptions = VendorSubscription::query()
            ->with(['vendor:id,uuid,shop_name,slug', 'recordedBy:id,name'])
            ->when(
                $statusFilter instanceof VendorSubscriptionStatus,
                fn (Builder $query): Builder => $query->where('status', $statusFilter),
            )
            ->when($from instanceof CarbonInterface, fn (Builder $query): Builder => $query->where('paid_at', '>=', $from))
            ->when($to instanceof CarbonInterface, fn (Builder $query): Builder => $query->where('paid_at', '<=', $to))
            ->latest('paid_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (VendorSubscription $subscription): array => [
                'id' => $subscription->uuid,
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'status' => $subscription->status->value,
                'starts_at' => $subscription->starts_at->toIso8601String(),
                'ends_at' => $subscription->ends_at->toIso8601String(),
                'payment_method' => $subscription->payment_method->value,
                'reference' => $subscription->reference,
                'paid_at' => $subscription->paid_at?->toIso8601String(),
                'recorded_by' => $subscription->recordedBy?->name,
                'vendor' => [
                    'id' => $subscription->vendor->uuid,
                    'shop_name' => $subscription->vendor->shop_name,
                    'slug' => $subscription->vendor->slug,
                ],
            ]);

        return Inertia::render('admin/subscriptions/index', [
            'subscriptions' => $subscriptions,

            // The headline number keeps the platform's definition: live periods with a
            // confirmed payment date. Never derived from orders.
            'revenue' => Inertia::defer(fn (): array => [
                'period' => $revenue->handle($from, $to),
                'all_time' => $revenue->handle(),
                'matching_filter' => $revenue->handle(
                    $from,
                    $to,
                    $statusFilter instanceof VendorSubscriptionStatus ? [$statusFilter] : null,
                ),
            ], 'revenue'),

            'upcomingExpiries' => Inertia::defer(
                fn (): array => $expiries->handle(withinDays: 45, limit: 12),
                'expiries',
            ),

            // The modal records against any approved shop, including ones whose period
            // has lapsed — that is exactly who is paying.
            'vendors' => Vendor::query()
                ->where('status', VendorStatus::Approved)
                ->orderBy('shop_name')
                ->get(['uuid', 'shop_name', 'subscription_status', 'subscription_ends_at'])
                ->map(fn (Vendor $vendor): array => [
                    'id' => $vendor->uuid,
                    'shop_name' => $vendor->shop_name,
                    'subscription_status' => $vendor->subscription_status->value,
                    'subscription_ends_at' => $vendor->subscription_ends_at?->toIso8601String(),
                ])
                ->all(),

            'fee' => [
                'amount' => $settings->subscriptionFee(),
                'currency' => $settings->subscriptionCurrency(),
                'days' => $settings->subscriptionDays(),
            ],

            'filters' => [
                'status' => $status,
                'period' => $period,
            ],
        ]);
    }

    /**
     * Record a payment an admin confirmed receiving off-platform.
     */
    public function store(SubscriptionPaymentRequest $request, RecordSubscriptionPayment $record): RedirectResponse
    {
        $this->authorize('create', VendorSubscription::class);

        $vendor = $request->vendor();

        $subscription = $record->handle(
            $vendor,
            $this->currentUser($request),
            $request->paymentMethod(),
            $request->string('reference')->toString(),
            $request->paidAt(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':amount recorded for :shop. Their shop is live until :date.', [
                'amount' => number_format($subscription->amount).' FRW',
                'shop' => $vendor->shop_name,
                'date' => $subscription->ends_at->toFormattedDateString(),
            ]),
        ]);

        return back();
    }

    /**
     * End a period early. Never a delete — this is the revenue ledger.
     */
    public function cancel(VendorSubscription $subscription, CancelSubscription $cancel): RedirectResponse
    {
        $this->authorize('cancel', $subscription);

        $cancel->handle($subscription);

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => __('Subscription cancelled. The row stays in the ledger and no refund is issued — subscription fees are non-refundable.'),
        ]);

        return back();
    }

    /**
     * Resolve the period filter into concrete bounds.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null, 2: string}
     */
    private function period(Request $request): array
    {
        $period = (string) $request->query('period', 'all');
        $now = Date::now();

        return match ($period) {
            'this_month' => [$now->copy()->startOfMonth(), $now, $period],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                $period,
            ],
            'this_year' => [$now->copy()->startOfYear(), $now, $period],
            'last_12_months' => [$now->copy()->subYear(), $now, $period],
            default => [null, null, 'all'],
        };
    }
}
