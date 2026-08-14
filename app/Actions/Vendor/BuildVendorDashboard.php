<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Models\VendorOrder;
use App\Support\Cast;
use Illuminate\Support\Facades\Config;
use stdClass;

/**
 * The headline numbers for a vendor's own shop.
 *
 * "Cash collected" is exactly that — money the vendor already took at the door. It is
 * never a balance or an amount owed, because the platform never touched it.
 *
 * Every query is scoped to the one vendor, and the counts are aggregates rather than
 * loaded collections so the dashboard stays cheap on a shop with a large catalog.
 */
final readonly class BuildVendorDashboard
{
    /**
     * @return array{
     *     orders_needing_action: int,
     *     orders_in_delivery: int,
     *     low_stock_count: int,
     *     out_of_stock_count: int,
     *     published_count: int,
     *     draft_count: int,
     *     cash_collected_this_month: int,
     *     delivered_this_month: int,
     *     active_delivery_areas: int,
     *     currency: string
     * }
     */
    public function handle(Vendor $vendor): array
    {
        $orders = $this->orderCounts($vendor);
        $catalog = $this->catalogCounts($vendor);

        return [
            // Everything the vendor still has to do something about: accept it, pack it
            // or deliver it.
            'orders_needing_action' => Cast::int($orders?->needing_action),
            'orders_in_delivery' => Cast::int($orders?->in_delivery),

            'low_stock_count' => Cast::int($catalog?->low_stock),
            'out_of_stock_count' => Cast::int($catalog?->out_of_stock),
            'published_count' => Cast::int($catalog?->published),
            'draft_count' => Cast::int($catalog?->draft),

            'cash_collected_this_month' => Cast::int($orders?->collected),
            'delivered_this_month' => Cast::int($orders?->delivered),

            // A vendor with no active coverage cannot be ordered from at all, however
            // much they have published, so the dashboard has to be able to say so.
            'active_delivery_areas' => VendorDeliveryArea::query()
                ->where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->count(),

            'currency' => Config::string('marketplace.currency'),
        ];
    }

    /**
     * Every vendor_orders figure on the dashboard, in one pass.
     *
     * These were six separate COUNT queries over two tables. Each one re-scanned the
     * same vendor's rows to answer a different question about them, and the six round
     * trips were serial — the dashboard could not render until the last one returned.
     * Conditional aggregation asks all of them at once, so the work is one pass per
     * table rather than one per number.
     */
    private function orderCounts(Vendor $vendor): ?stdClass
    {
        // Snapshotted once rather than re-read per binding: each call reaches for
        // now(), so a request crossing midnight on the last day of a month could
        // otherwise count deliveries in one window and sum their cash in the next,
        // and report a delivery count against a total from a different month.
        $monthStart = $this->monthStart();
        $monthEnd = $this->monthEnd();

        return VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->toBase()
            ->selectRaw(
                <<<'SQL'
                    COUNT(CASE WHEN status IN (?, ?, ?) THEN 1 END) as needing_action,
                    COUNT(CASE WHEN status = ? THEN 1 END) as in_delivery,
                    COUNT(CASE WHEN status = ? AND delivered_at BETWEEN ? AND ? THEN 1 END) as delivered,
                    COALESCE(SUM(CASE WHEN status = ? AND delivered_at BETWEEN ? AND ? THEN total END), 0) as collected
                    SQL,
                [
                    OrderStatus::Pending->value,
                    OrderStatus::Confirmed->value,
                    OrderStatus::Processing->value,
                    OrderStatus::Shipped->value,
                    OrderStatus::Delivered->value,
                    $monthStart,
                    $monthEnd,
                    OrderStatus::Delivered->value,
                    $monthStart,
                    $monthEnd,
                ],
            )
            ->first();
    }

    /**
     * Every products figure on the dashboard, in one pass.
     */
    private function catalogCounts(Vendor $vendor): ?stdClass
    {
        return Product::query()
            ->where('vendor_id', $vendor->id)
            ->toBase()
            ->selectRaw(
                <<<'SQL'
                    COUNT(CASE WHEN status <> ? AND stock_quantity <= low_stock_threshold THEN 1 END) as low_stock,
                    COUNT(CASE WHEN status <> ? AND stock_quantity = 0 THEN 1 END) as out_of_stock,
                    COUNT(CASE WHEN status = ? THEN 1 END) as published,
                    COUNT(CASE WHEN status = ? THEN 1 END) as draft
                    SQL,
                [
                    ProductStatus::Archived->value,
                    ProductStatus::Archived->value,
                    ProductStatus::Published->value,
                    ProductStatus::Draft->value,
                ],
            )
            ->first();
    }

    private function monthStart(): string
    {
        return now()->startOfMonth()->toDateTimeString();
    }

    private function monthEnd(): string
    {
        return now()->endOfMonth()->toDateTimeString();
    }
}
