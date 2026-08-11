<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\PresentsOrders;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only oversight of every order on the platform.
 *
 * Admins can see everything but advance nothing: moving a vendor order to shipped or
 * delivered is a claim about goods physically changing hands, and only the vendor who
 * carried them can make it. The totals here are gross merchandise value — cash the
 * customer hands the vendor at the door — not platform income.
 */
final class OrderController extends Controller
{
    use PresentsOrders;

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = mb_trim($request->string('search')->toString());
        $from = $request->date('from');
        $to = $request->date('to');

        $orders = Order::query()
            ->with(['user:id,name,email', 'vendorOrders:id,uuid,order_id,vendor_id,status,total', 'vendorOrders.vendor:id,shop_name'])
            ->when(
                OrderStatus::tryFrom($status) instanceof OrderStatus,
                fn (Builder $query): Builder => $query->where('status', $status),
            )
            ->when($search !== '', fn (Builder $query): Builder => $query->where('order_number', 'like', "%{$search}%"))
            ->when($from !== null, fn (Builder $query): Builder => $query->where('placed_at', '>=', $from))
            ->when($to !== null, fn (Builder $query): Builder => $query->where('placed_at', '<=', $to))
            ->latest('placed_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'total' => $order->total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'customer_name' => $order->user->name,
                'vendor_orders' => $order->vendorOrders
                    ->map(fn (VendorOrder $vendorOrder): array => [
                        'id' => $vendorOrder->uuid,
                        'status' => $vendorOrder->status->value,
                        'total' => $vendorOrder->total,
                        'shop_name' => $vendorOrder->vendor->shop_name,
                    ])
                    ->all(),
            ]);

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'user:id,name,email,phone',
            'shippingAddress.district:id,name',
            'shippingAddress.sector:id,name',
            'vendorOrders.vendor:id,uuid,shop_name,slug,phone',
            'vendorOrders.items',
        ]);

        return Inertia::render('admin/orders/show', [
            'order' => [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping_fee' => $order->shipping_fee,
                'tax' => $order->tax,
                'total' => $order->total,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method->value,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'customer' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ],
                'shipping_address' => $this->deliveryLabel($order->shippingAddress),
                'vendor_orders' => $order->vendorOrders
                    ->map(fn (VendorOrder $vendorOrder): array => [
                        'id' => $vendorOrder->uuid,
                        'order_number' => $vendorOrder->order_number,
                        'status' => $vendorOrder->status->value,
                        ...$this->vendorOrderTotals($vendorOrder),
                        'delivered_at' => $vendorOrder->delivered_at?->toIso8601String(),
                        'vendor' => [
                            'id' => $vendorOrder->vendor->uuid,
                            'shop_name' => $vendorOrder->vendor->shop_name,
                            'phone' => $vendorOrder->vendor->phone,
                        ],
                        'items' => $vendorOrder->items
                            ->map(fn (OrderItem $item): array => [
                                ...$this->orderItemLine($item),
                                'sku' => $item->sku,
                            ])
                            ->all(),
                    ])
                    ->all(),
            ],
        ]);
    }
}
