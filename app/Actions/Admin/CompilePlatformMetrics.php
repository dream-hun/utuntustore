<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Support\Cast;

/**
 * The headline health numbers for the admin dashboard.
 *
 * Deliberately holds no money figure: revenue has exactly one definition and it lives
 * in {@see CalculatePlatformRevenue}.
 */
final readonly class CompilePlatformMetrics
{
    /**
     * @return array{
     *     vendors: array{total: int, pending: int, approved: int, rejected: int, suspended: int},
     *     selling: array{active: int, grace: int, expired: int, none: int},
     *     active_subscriptions: int,
     *     customers: int
     * }
     */
    public function handle(): array
    {
        /** @var array<string, int> $byStatus */
        $byStatus = Vendor::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => Cast::int($count))
            ->all();

        /** @var array<string, int> $bySelling */
        $bySelling = Vendor::query()
            ->toBase()
            ->selectRaw('subscription_status, count(*) as aggregate')
            ->groupBy('subscription_status')
            ->pluck('aggregate', 'subscription_status')
            ->map(static fn (mixed $count): int => Cast::int($count))
            ->all();

        return [
            'vendors' => [
                'total' => array_sum($byStatus),
                'pending' => $byStatus[VendorStatus::Pending->value] ?? 0,
                'approved' => $byStatus[VendorStatus::Approved->value] ?? 0,
                'rejected' => $byStatus[VendorStatus::Rejected->value] ?? 0,
                'suspended' => $byStatus[VendorStatus::Suspended->value] ?? 0,
            ],
            'selling' => [
                'active' => $bySelling[SubscriptionStatus::Active->value] ?? 0,
                'grace' => $bySelling[SubscriptionStatus::Grace->value] ?? 0,
                'expired' => $bySelling[SubscriptionStatus::Expired->value] ?? 0,
                'none' => $bySelling[SubscriptionStatus::None->value] ?? 0,
            ],
            'active_subscriptions' => VendorSubscription::query()
                ->where('status', VendorSubscriptionStatus::Active)
                ->count(),
            'customers' => User::query()
                ->where('role', UserRole::Customer)
                ->count(),
        ];
    }
}
