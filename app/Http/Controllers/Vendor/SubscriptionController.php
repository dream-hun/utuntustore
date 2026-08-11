<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The vendor's own view of the platform's only revenue stream.
 *
 * Read-only on purpose. The fee is paid off-platform by mobile money, bank transfer
 * or cash, and only an admin who confirmed receiving the money can record it — there
 * is no self-service subscribe or cancel to build, because the platform never takes
 * a payment.
 *
 * This route must stay reachable when the subscription has expired. It is the one
 * screen an expired vendor most needs, so it carries no `vendor.can-sell` guard.
 */
final class SubscriptionController extends Controller
{
    public function __invoke(Vendor $vendor, Settings $settings): Response
    {
        $this->authorize('viewAny', VendorSubscription::class);

        return Inertia::render('vendor/subscription', [
            'subscription' => [
                'status' => $vendor->subscription_status->value,
                'ends_at' => $vendor->subscription_ends_at,
                'can_sell' => $vendor->canSell(),
                'is_platform_owned' => $vendor->is_platform_owned,
            ],
            'terms' => [
                'fee' => $settings->subscriptionFee(),
                'currency' => $settings->subscriptionCurrency(),
                'days' => $settings->subscriptionDays(),
                'grace_days' => $settings->subscriptionGraceDays(),
            ],

            'payments' => Inertia::defer(fn (): array => $vendor->subscriptions()
                ->latest('starts_at')
                ->limit(24)
                ->get()
                ->map(fn (VendorSubscription $subscription): array => [
                    'id' => $subscription->uuid,
                    'amount' => $subscription->amount,
                    'currency' => $subscription->currency,
                    'status' => $subscription->status->value,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                    'payment_method' => $subscription->payment_method->value,
                    'reference' => $subscription->reference,
                    'paid_at' => $subscription->paid_at,
                ])
                ->all()),
        ]);
    }
}
