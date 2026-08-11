<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\VendorSubscription;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Ends a subscription period early.
 *
 * Subscription rows are the platform's revenue ledger and are never hard-deleted, so
 * cancelling is the only way to stop one. Cancelling issues no refund (BR-15): the fee
 * is non-refundable and any goodwill payment is an operational exception handled
 * outside the system.
 *
 * The paid period is closed at the moment of cancellation rather than erased, which
 * hands the vendor to the normal lapse path — grace, then expiry — and keeps this
 * action's result stable when the daily sweep next recomputes eligibility from
 * `subscription_ends_at`. Cancelling is a billing action; to stop a shop selling
 * immediately, suspend the vendor instead.
 */
final readonly class CancelSubscription
{
    public function __construct(private Settings $settings) {}

    public function handle(VendorSubscription $subscription): VendorSubscription
    {
        return DB::transaction(function () use ($subscription): VendorSubscription {
            $wasLive = $subscription->status === VendorSubscriptionStatus::Active;

            $subscription->status = VendorSubscriptionStatus::Cancelled;
            $subscription->save();

            if ($wasLive) {
                $vendor = $subscription->vendor;
                $vendor->subscription_ends_at = now();
                $vendor->subscription_status = $this->settings->subscriptionGraceDays() > 0
                    ? SubscriptionStatus::Grace
                    : SubscriptionStatus::Expired;
                $vendor->save();
            }

            return $subscription;
        });
    }
}
