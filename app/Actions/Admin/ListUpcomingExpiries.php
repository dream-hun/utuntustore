<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Models\Vendor;

/**
 * Vendors whose selling rights are about to lapse.
 *
 * This is the platform's renewal pipeline: every row here is a shop that disappears
 * from the storefront unless someone collects 20,000 RWF off-platform and records it.
 * Vendors already in grace are included first — they have lapsed but are still selling,
 * so they are the most urgent call to make.
 */
final readonly class ListUpcomingExpiries
{
    /**
     * @return array<int, array{
     *     id: string,
     *     shop_name: string,
     *     phone: string,
     *     subscription_status: string,
     *     subscription_ends_at: string|null
     * }>
     */
    public function handle(int $withinDays = 30, int $limit = 10): array
    {
        return Vendor::query()
            ->where('status', VendorStatus::Approved)
            ->where('is_platform_owned', false)
            ->whereIn('subscription_status', [SubscriptionStatus::Active, SubscriptionStatus::Grace])
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<=', now()->addDays($withinDays))
            ->oldest('subscription_ends_at')
            ->limit($limit)
            ->get()
            ->map(fn (Vendor $vendor): array => [
                'id' => $vendor->uuid,
                'shop_name' => $vendor->shop_name,
                'phone' => $vendor->phone,
                'subscription_status' => $vendor->subscription_status->value,
                'subscription_ends_at' => $vendor->subscription_ends_at?->toIso8601String(),
            ])
            ->all();
    }
}
