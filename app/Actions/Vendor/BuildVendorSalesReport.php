<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\Cast;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Reports the cash a vendor collected at the door over a period.
 *
 * This is a record of money that already changed hands directly between the customer
 * and the vendor. The platform never held any of it, so there is no balance, no
 * pending settlement and nothing owed in either direction — the only figure the
 * platform is a party to is the vendor's own subscription, which is a cost to them.
 *
 * Only delivered vendor orders count: `delivered` is the vendor's own report that
 * they handed the goods over and took the cash.
 */
final readonly class BuildVendorSalesReport
{
    public function __construct(private Settings $settings) {}

    /**
     * @return array{
     *     collected: int,
     *     order_count: int,
     *     item_count: int,
     *     average_order: int,
     *     cancelled_count: int,
     *     cancelled_value: int,
     *     currency: string,
     *     by_month: array<int, array{month: string, collected: int, order_count: int}>,
     *     top_products: array<int, array{name: string, quantity: int, collected: int}>,
     *     subscription_fee: int,
     *     subscription_ends_at: string|null
     * }
     */
    public function handle(Vendor $vendor, CarbonInterface $from, CarbonInterface $to): array
    {
        $delivered = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('delivered_at', [$from, $to]);

        // toBase() keeps these as plain result rows: they are report aggregates, not
        // VendorOrder entities, and hydrating a model gives phantom attributes that no
        // longer correspond to any column.
        $totals = (clone $delivered)
            ->toBase()
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as collected')
            ->first();

        $orderCount = Cast::int($totals?->order_count);
        $collected = Cast::int($totals?->collected);

        $cancelled = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', OrderStatus::Cancelled)
            ->whereBetween('updated_at', [$from, $to])
            ->toBase()
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as value')
            ->first();

        return [
            'collected' => $collected,
            'order_count' => $orderCount,
            'item_count' => Cast::int(OrderItem::query()
                ->whereIn('vendor_order_id', (clone $delivered)->select('id'))
                ->sum('quantity')),
            'average_order' => $orderCount === 0 ? 0 : intdiv($collected, $orderCount),
            'cancelled_count' => Cast::int($cancelled?->order_count),
            'cancelled_value' => Cast::int($cancelled?->value),
            'currency' => Config::string('marketplace.currency'),
            'by_month' => $this->byMonth($vendor, $from, $to),
            'top_products' => $this->topProducts($vendor, $from, $to),
            'subscription_fee' => $this->settings->subscriptionFee(),
            'subscription_ends_at' => $vendor->subscription_ends_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{month: string, collected: int, order_count: int}>
     */
    private function byMonth(Vendor $vendor, CarbonInterface $from, CarbonInterface $to): array
    {
        // SQLite (tests) and MySQL (production) spell month truncation differently, and
        // this is the only place in the vendor area that needs it.
        $month = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', delivered_at)" : "DATE_FORMAT(delivered_at, '%Y-%m')";

        return VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('delivered_at', [$from, $to])
            ->toBase()
            ->selectRaw("{$month} as month, COALESCE(SUM(total), 0) as collected, COUNT(*) as order_count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn (object $row): array => [
                'month' => Cast::string($row->month),
                'collected' => Cast::int($row->collected),
                'order_count' => Cast::int($row->order_count),
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, quantity: int, collected: int}>
     */
    private function topProducts(Vendor $vendor, CarbonInterface $from, CarbonInterface $to): array
    {
        return OrderItem::query()
            ->whereIn('vendor_order_id', VendorOrder::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', OrderStatus::Delivered)
                ->whereBetween('delivered_at', [$from, $to])
                ->select('id'))
            ->toBase()
            ->selectRaw('product_name, SUM(quantity) as quantity, SUM(subtotal) as collected')
            ->groupBy('product_name')
            ->orderByDesc('collected')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'name' => Cast::string($row->product_name),
                'quantity' => Cast::int($row->quantity),
                'collected' => Cast::int($row->collected),
            ])
            ->all();
    }
}
