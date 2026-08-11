<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\VendorSubscriptionStatus;
use App\Models\VendorSubscription;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single definition of platform revenue.
 *
 * Revenue is the sum of paid vendor subscriptions and nothing else. Order totals are
 * gross merchandise value: the customer hands that cash to the vendor at the door and
 * the platform never takes a share of it, so counting orders here would invent income
 * the business does not have. Every admin figure that says "revenue" comes through
 * this action.
 */
final readonly class CalculatePlatformRevenue
{
    public function __construct(private Settings $settings) {}

    /**
     * Revenue recognised in a period, keyed on when the money was received.
     *
     * @param  CarbonInterface|null  $from  Inclusive lower bound on `paid_at`.
     * @param  CarbonInterface|null  $to  Inclusive upper bound on `paid_at`.
     * @param  array<int, VendorSubscriptionStatus>|null  $statuses  Defaults to live periods only.
     * @return array{total: int, count: int, currency: string}
     */
    public function handle(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        ?array $statuses = null,
    ): array {
        $query = $this->query($from, $to, $statuses);

        return [
            'total' => (int) $query->sum('amount'),
            'count' => $this->query($from, $to, $statuses)->count(),
            'currency' => $this->settings->subscriptionCurrency(),
        ];
    }

    /**
     * @param  array<int, VendorSubscriptionStatus>|null  $statuses
     * @return Builder<VendorSubscription>
     */
    private function query(
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        ?array $statuses,
    ): Builder {
        $query = VendorSubscription::query()
            ->whereNotNull('paid_at')
            ->whereIn('status', array_map(
                static fn (VendorSubscriptionStatus $status): string => $status->value,
                $statuses ?? [VendorSubscriptionStatus::Active],
            ));

        if ($from instanceof CarbonInterface) {
            $query->where('paid_at', '>=', $from);
        }

        if ($to instanceof CarbonInterface) {
            $query->where('paid_at', '<=', $to);
        }

        return $query;
    }
}
