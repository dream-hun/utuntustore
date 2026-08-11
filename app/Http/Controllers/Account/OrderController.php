<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Account\CancelOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\PresentsOrders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\OrderIndexRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\VendorOrder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A customer's own order history.
 *
 * A multi-vendor order is shown as its vendor orders, because that is how the
 * customer actually experiences it: three shops delivering separately, each paid
 * in cash at the door.
 */
final class OrderController extends Controller
{
    use PresentsOrders;

    public function index(OrderIndexRequest $request): Response
    {
        $status = $request->status();
        $user = $this->currentUser($request);

        $query = $user->orders()->with(['vendorOrders.vendor']);

        if ($status instanceof OrderStatus) {
            $query->where('status', $status);
        }

        $orders = $query
            ->latest('placed_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'total' => $order->total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at,
                'can_cancel' => $user->can('cancel', $order),
                'vendor_orders' => $order->vendorOrders
                    ->map(fn (VendorOrder $vendorOrder): array => [
                        'id' => $vendorOrder->uuid,
                        'order_number' => $vendorOrder->order_number,
                        'status' => $vendorOrder->status->value,
                        'total' => $vendorOrder->total,
                        'vendor' => [
                            'shop_name' => $vendorOrder->vendor->shop_name,
                            'slug' => $vendorOrder->vendor->slug,
                        ],
                    ])
                    ->all(),
            ]);

        return Inertia::render('account/orders/index', [
            'orders' => $orders,
            'filters' => ['status' => $status?->value],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $user = $this->currentUser($request);

        // `order` and `vendorOrder` are loaded on the items because ReviewPolicy reads
        // both when deciding whether a line can still be reviewed.
        $order->load([
            'vendorOrders.vendor',
            'vendorOrders.items.product',
            'vendorOrders.items.order',
            'vendorOrders.items.vendorOrder',
            'shippingAddress.district',
            'shippingAddress.sector',
        ]);

        return Inertia::render('account/orders/show', [
            'order' => [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping_fee' => $order->shipping_fee,
                'total' => $order->total,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method->value,
                'placed_at' => $order->placed_at,
                'can_cancel' => $user->can('cancel', $order),
                'shipping_address' => $this->deliveryLabel($order->shippingAddress),
                'vendor_orders' => $order->vendorOrders
                    ->map(fn (VendorOrder $vendorOrder): array => [
                        'id' => $vendorOrder->uuid,
                        'order_number' => $vendorOrder->order_number,
                        'status' => $vendorOrder->status->value,
                        'subtotal' => $vendorOrder->subtotal,
                        'shipping_fee' => $vendorOrder->shipping_fee,
                        'total' => $vendorOrder->total,
                        'delivered_at' => $vendorOrder->delivered_at,
                        'vendor' => [
                            'shop_name' => $vendorOrder->vendor->shop_name,
                            'slug' => $vendorOrder->vendor->slug,
                            'phone' => $vendorOrder->vendor->phone,
                        ],
                        'items' => $vendorOrder->items->map(fn (OrderItem $item): array => [
                            ...$this->orderItemLine($item),
                            // Null once a product has been deleted from the catalog;
                            // the order line keeps its snapshot either way.
                            'product_id' => $item->product?->uuid,
                            'can_review' => $user->can('createForOrderItem', [Review::class, $item]),
                        ])->all(),
                    ])
                    ->all(),
            ],
        ]);
    }

    /**
     * Cancel what has not shipped yet, restoring the stock it was holding.
     */
    public function cancel(Request $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $this->authorize('cancel', $order);

        try {
            $cancelOrder->handle($order);
        } catch (DomainException $domainException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $domainException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled.')]);

        return back();
    }
}
