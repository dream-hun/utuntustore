<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a subscription payment an admin has confirmed receiving off-platform.
 *
 * This is the platform's entire revenue pipeline. Money arrives by mobile money, bank
 * transfer or cash, outside the system; the admin who confirmed it records it here and
 * the vendor's shop is restored with its catalog intact.
 */
final readonly class RecordSubscriptionPayment
{
    public function __construct(private Settings $settings) {}

    public function handle(
        Vendor $vendor,
        User $recordedBy,
        SubscriptionPaymentMethod $paymentMethod,
        ?string $reference = null,
        ?CarbonImmutable $paidAt = null,
    ): VendorSubscription {
        return DB::transaction(function () use ($vendor, $recordedBy, $paymentMethod, $reference, $paidAt): VendorSubscription {
            $paidAt ??= now();

            $current = $vendor->subscriptions()
                ->where('status', VendorSubscriptionStatus::Active)
                ->lockForUpdate()
                ->latest('ends_at')
                ->first();

            // A renewal begins where the previous period ended, so a vendor who pays
            // early is never charged for time they already own. Periods must not overlap.
            $startsAt = $current instanceof VendorSubscription && $current->ends_at->isFuture()
                ? $current->ends_at
                : $paidAt;

            // Only one subscription may be active per vendor, so the outgoing period is
            // closed off as the new one takes over. The row is kept, never deleted —
            // it is a line in the platform's revenue ledger.
            if ($current instanceof VendorSubscription) {
                $current->status = VendorSubscriptionStatus::Expired;
                $current->save();
            }

            $subscription = new VendorSubscription;
            $subscription->vendor_id = $vendor->id;

            // Copied from the configured fee at creation time so a later price change
            // never rewrites this row.
            $subscription->amount = $this->settings->subscriptionFee();
            $subscription->currency = $this->settings->subscriptionCurrency();
            $subscription->status = VendorSubscriptionStatus::Active;
            $subscription->starts_at = $startsAt;
            $subscription->ends_at = $startsAt->copy()->addDays($this->settings->subscriptionDays());
            $subscription->payment_method = $paymentMethod;
            $subscription->reference = $reference;
            $subscription->paid_at = $paidAt;
            $subscription->recorded_by = $recordedBy->id;
            $subscription->save();

            $this->denormalizeOntoVendor($vendor, $subscription);

            return $subscription;
        });
    }

    /**
     * Mirror the new period onto the vendor so storefront eligibility stays a single
     * indexed condition rather than a join on every catalog query.
     */
    private function denormalizeOntoVendor(Vendor $vendor, VendorSubscription $subscription): void
    {
        $vendor->subscription_status = SubscriptionStatus::Active;
        $vendor->subscription_ends_at = $subscription->ends_at;
        $vendor->save();
    }
}
