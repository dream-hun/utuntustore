<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\Vendor;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the denormalized subscription columns on vendors.
 *
 * Expiring never deletes or unpublishes anything. It only makes the vendor ineligible
 * to sell, which the storefront enforces through the eligibility condition — so paying
 * again restores the shop exactly as it was, catalog and all.
 *
 * The sweep is idempotent by construction: it derives each vendor's state purely from
 * `subscription_ends_at` and the clock, so a missed run, a repeated run, or two runs
 * racing each other all converge on the same answer.
 */
final readonly class SweepSubscriptions
{
    public function __construct(private Settings $settings) {}

    /**
     * @return array{active: int, grace: int, expired: int}
     */
    public function handle(): array
    {
        $graceDays = $this->settings->subscriptionGraceDays();
        $now = now();
        $counts = ['active' => 0, 'grace' => 0, 'expired' => 0];

        Vendor::query()
            ->where('is_platform_owned', false)
            ->whereNotNull('subscription_ends_at')
            ->whereIn('subscription_status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Grace,
                SubscriptionStatus::Expired,
            ])
            ->chunkById(200, function ($vendors) use ($now, $graceDays, &$counts): void {
                foreach ($vendors as $vendor) {
                    $endsAt = $vendor->subscription_ends_at;

                    // whereNotNull() above already excludes these, so this only guards
                    // against a row changing under a long-running chunked sweep.
                    if (! $endsAt instanceof CarbonImmutable) {
                        continue;
                    }

                    $target = match (true) {
                        $endsAt->greaterThan($now) => SubscriptionStatus::Active,
                        $endsAt->copy()->addDays($graceDays)->greaterThan($now) => SubscriptionStatus::Grace,
                        default => SubscriptionStatus::Expired,
                    };

                    $counts[$target->value]++;

                    DB::transaction(function () use ($vendor, $target): void {
                        if ($vendor->subscription_status !== $target) {
                            $vendor->subscription_status = $target;
                            $vendor->save();
                        }

                        // Reconciled on every pass, not only when the vendor column
                        // changed. The two can drift apart — a vendor set expired by
                        // hand, or a run that died between the two writes — and a stale
                        // active row would keep counting towards platform revenue for a
                        // subscription that has actually lapsed.
                        if ($target === SubscriptionStatus::Expired) {
                            $vendor->subscriptions()
                                ->where('status', VendorSubscriptionStatus::Active)
                                ->update(['status' => VendorSubscriptionStatus::Expired]);
                        }
                    });
                }
            });

        return $counts;
    }

    /**
     * Vendors whose selling rights are about to lapse, for expiry reminders.
     *
     * @return Collection<int, Vendor>
     */
    public function expiringIn(int $days): Collection
    {
        return Vendor::query()
            ->where('status', VendorStatus::Approved)
            ->where('is_platform_owned', false)
            ->where('subscription_status', SubscriptionStatus::Active)
            ->whereBetween('subscription_ends_at', [
                now()->addDays($days)->startOfDay(),
                now()->addDays($days)->endOfDay(),
            ])
            ->get();
    }
}
