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
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $thisMonth = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('delivered_at', [$monthStart, $monthEnd])
            ->toBase()
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as collected')
            ->first();

        return [
            // Everything the vendor still has to do something about: accept it, pack it
            // or deliver it.
            'orders_needing_action' => VendorOrder::query()
                ->where('vendor_id', $vendor->id)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing])
                ->count(),

            'orders_in_delivery' => VendorOrder::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', OrderStatus::Shipped)
                ->count(),

            'low_stock_count' => Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', '!=', ProductStatus::Archived)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->count(),

            'out_of_stock_count' => Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', '!=', ProductStatus::Archived)
                ->where('stock_quantity', 0)
                ->count(),

            'published_count' => Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', ProductStatus::Published)
                ->count(),

            'draft_count' => Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', ProductStatus::Draft)
                ->count(),

            'cash_collected_this_month' => Cast::int($thisMonth?->collected),
            'delivered_this_month' => Cast::int($thisMonth?->order_count),

            // A vendor with no active coverage cannot be ordered from at all, however
            // much they have published, so the dashboard has to be able to say so.
            'active_delivery_areas' => VendorDeliveryArea::query()
                ->where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->count(),

            'currency' => Config::string('marketplace.currency'),
        ];
    }
}
