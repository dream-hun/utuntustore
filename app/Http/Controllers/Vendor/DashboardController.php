<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendor\BuildVendorDashboard;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\Cast;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The vendor's own shop at a glance.
 *
 * Reachable with an expired subscription by design: a vendor who has lapsed still has
 * orders to deliver and a catalog to look after, and the fastest way to get them to
 * renew is to show them what they are missing rather than lock the door.
 *
 * The aggregates are deferred so the page frame and the subscription banner render
 * immediately — this dashboard is opened on a phone on a slow connection far more
 * often than it is opened on a desk.
 */
final class DashboardController extends Controller
{
    public function __invoke(Vendor $vendor, BuildVendorDashboard $dashboard, Settings $settings): Response
    {
        return Inertia::render('vendor/dashboard', [
            'shopName' => $vendor->shop_name,
            'subscriptionFee' => $settings->subscriptionFee(),

            'stats' => Inertia::defer(fn (): array => $dashboard->handle($vendor)),

            'actionableOrders' => Inertia::defer(fn (): array => VendorOrder::query()
                ->where('vendor_id', $vendor->id)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped])
                ->withCount('items')
                ->latest('created_at')
                ->limit(6)
                ->get()
                ->map(fn (VendorOrder $vendorOrder): array => [
                    'id' => $vendorOrder->uuid,
                    'order_number' => $vendorOrder->order_number,
                    'status' => $vendorOrder->status->value,
                    'total' => $vendorOrder->total,
                    'item_count' => Cast::int($vendorOrder->getAttribute('items_count')),
                    'created_at' => $vendorOrder->created_at,
                ])
                ->all()),

            'lowStockProducts' => Inertia::defer(fn (): array => Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', '!=', ProductStatus::Archived)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->orderBy('stock_quantity')
                ->limit(6)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->uuid,
                    'name' => $product->name,
                    'stock_quantity' => $product->stock_quantity,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'status' => $product->status->value,
                ])
                ->all()),
        ]);
    }
}
