<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Orders\TransitionVendorOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\PresentsOrders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\TransitionVendorOrderRequest;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\Cast;
use App\Support\Money;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The vendor's own slice of customer orders.
 *
 * The marketplace's isolation boundary runs through this controller. A vendor reaches
 * a customer's name, phone number and address only through a VendorOrder that is
 * theirs — never through the parent Order, which is why there is no vendor route to an
 * Order at all and why every query below is anchored on vendor_id.
 *
 * The customer's contact details are shown rather than hidden because the vendor is
 * the one who has to find the house and collect the cash. There is no courier layer
 * to relay them through.
 */
final class OrderController extends Controller
{
    use PresentsOrders;

    public function index(Request $request, Vendor $vendor): Response
    {
        $this->authorize('viewAny', VendorOrder::class);

        $search = mb_trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $orders = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->withCount('items')
            ->with(['order.user'])
            ->when($search !== '', fn (Builder $query) => $query->where('order_number', 'like', "%{$search}%"))
            ->when(
                in_array($status, array_column(OrderStatus::cases(), 'value'), true),
                fn (Builder $query) => $query->where('status', $status),
            )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (VendorOrder $vendorOrder): array => [
                'id' => $vendorOrder->uuid,
                'order_number' => $vendorOrder->order_number,
                ...$this->vendorOrderStatus($vendorOrder),
                'subtotal' => $vendorOrder->subtotal,
                'shipping_fee' => $vendorOrder->shipping_fee,
                'total' => $vendorOrder->total,
                'item_count' => Cast::int($vendorOrder->getAttribute('items_count')),
                'delivered_at' => $vendorOrder->delivered_at,
                'created_at' => $vendorOrder->created_at,
                'customer_name' => $vendorOrder->order->user->name,
            ]);

        return Inertia::render('vendor/orders/index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(VendorOrder $vendorOrder): Response
    {
        $this->authorize('view', $vendorOrder);

        $vendorOrder->load([
            'items',
            'order.user',
            'order.shippingAddress.district',
            'order.shippingAddress.sector',
        ]);

        return Inertia::render('vendor/orders/show', [
            'vendorOrder' => [
                'id' => $vendorOrder->uuid,
                'order_number' => $vendorOrder->order_number,
                ...$this->vendorOrderStatus($vendorOrder),
                ...$this->vendorOrderTotals($vendorOrder),
                'tax' => $vendorOrder->tax,
                'currency' => $vendorOrder->order->currency,
                'delivered_at' => $vendorOrder->delivered_at,
                'created_at' => $vendorOrder->created_at,
                'placed_at' => $vendorOrder->order->placed_at,

                // The customer's own order number, so a phone call about "order 12345"
                // can be matched. Nothing else about the parent order is exposed, and
                // in particular nothing belonging to another vendor in the same basket.
                'customer_order_number' => $vendorOrder->order->order_number,

                'customer' => [
                    'name' => $vendorOrder->order->user->name,
                    'phone' => $vendorOrder->order->shippingAddress->phone,
                ],

                'delivery_address' => $this->deliveryLabel($vendorOrder->order->shippingAddress),

                'items' => $vendorOrder->items
                    ->map(fn (OrderItem $item): array => [
                        ...$this->orderItemLine($item),
                        'sku' => $item->sku,
                    ])
                    ->all(),
            ],
        ]);
    }

    public function update(
        TransitionVendorOrderRequest $request,
        VendorOrder $vendorOrder,
        TransitionVendorOrder $transitionVendorOrder,
    ): RedirectResponse {
        $this->authorize('update', $vendorOrder);

        $status = $request->status();

        try {
            $transitionVendorOrder->handle($vendorOrder, $status);
        } catch (DomainException $domainException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $domainException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $status === OrderStatus::Delivered
                ? __('Order marked delivered. You collected :total in cash.', ['total' => Money::format($vendorOrder->total)])
                : __('Order moved to :status.', ['status' => $status->label()]),
        ]);

        return back();
    }
}
