<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Cart\ResolveCart;
use App\Actions\Checkout\BuildCheckoutQuote;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Storefront\CreateDeliveryAddress;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Concerns\PresentsOrders;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\PresentsCheckout;
use App\Http\Requests\Storefront\PlaceOrderRequest;
use App\Http\Requests\Storefront\StoreCheckoutAddressRequest;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\VendorOrder;
use App\Support\Checkout\CheckoutQuote;
use App\Support\LocationDirectory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout, and the confirmation that follows it.
 *
 * Nothing here prices, validates or writes anything itself: BuildCheckoutQuote is
 * the single source of truth for what a cart costs, and PlaceOrder is the only
 * thing that turns one into an order. The screen renders exactly the quote that
 * will be persisted, so a customer can never confirm one set of numbers and be
 * held to another.
 *
 * Payment is cash on delivery, collected per vendor at the door. The platform
 * never holds or transfers any of it.
 */
final class CheckoutController extends Controller
{
    use PresentsCheckout;
    use PresentsOrders;

    public function __construct(
        private readonly ResolveCart $carts,
        private readonly BuildCheckoutQuote $quotes,
        private readonly LocationDirectory $locations,
    ) {}

    public function index(Request $request): Response
    {
        $cart = $this->carts->handle($request);

        $addresses = $this->currentUser($request)->addresses()
            ->with(['district', 'sector'])
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        $address = $this->selectedAddress($request, $addresses);
        $code = mb_trim($request->string('coupon')->value());
        $coupon = $this->findCoupon($code);

        // Built at most once per request and shared by both props, so a partial
        // reload for the sector list never re-prices the whole basket.
        $quote = null;
        $resolveQuote = function () use (&$quote, $cart, $address, $coupon): CheckoutQuote {
            return $quote ??= $this->quotes->handle($cart, $address, $coupon);
        };

        return Inertia::render('storefront/checkout', [
            'quote' => fn (): array => $this->quoteProps($resolveQuote()),

            'addresses' => fn (): array => $addresses
                ->map(fn (Address $address): array => $this->addressProps($address))
                ->all(),

            'selectedAddressId' => $address?->uuid,

            'coupon' => fn (): ?array => $code === '' ? null : $this->couponProps($code, $resolveQuote()),

            'districts' => $this->locations->districtOptions(...),

            // Scoped to the district being filled in, never all 416 sectors.
            'sectors' => fn (): array => $this->locations->sectorOptions($request->string('district')->value()),
        ]);
    }

    /**
     * Add a delivery address without leaving checkout, then re-quote against it.
     */
    public function storeAddress(StoreCheckoutAddressRequest $request, CreateDeliveryAddress $createAddress): RedirectResponse
    {
        $address = $createAddress->handle($this->currentUser($request), $request->deliveryAddress());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery address saved.')]);

        return to_route('checkout.index', array_filter([
            'address' => $address->uuid,
            'coupon' => $request->couponCode(),
        ]));
    }

    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $cart = $this->carts->handle($request);

        $address = $this->currentUser($request)->addresses()
            ->where('uuid', $request->string('address_id')->toString())
            ->firstOrFail();

        $coupon = $this->findCoupon(
            $request->filled('coupon_code') ? $request->string('coupon_code')->toString() : null,
        );

        // Re-quoted rather than trusting anything posted: stock, prices and vendor
        // eligibility can all have moved since the screen was rendered.
        $quote = $this->quotes->handle($cart, $address, $coupon);

        // An order is placed at the total the customer agreed to, or not at all.
        //
        // A price change is deliberately not a blocking problem — the cart's unit_price
        // is only a display snapshot, and nothing refreshes it, so treating one as
        // blocking would wedge the customer on the checkout screen with no way through
        // but emptying their basket. Instead the screen posts the total it showed, and
        // a re-quote that disagrees sends them back to look at the new figure rather
        // than charging it. Confirming again posts the new total and goes through.
        //
        // This also covers a coupon that lapsed between render and submit, which
        // silently yields a discount of zero and records no problem at all.
        if ($quote->total !== $request->expectedTotal()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Prices changed while you were checking out. Please review your order and confirm again.'),
            ]);

            return back();
        }

        try {
            $order = $placeOrder->handle($this->currentUser($request), $cart, $quote);
        } catch (CheckoutException $checkoutException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $checkoutException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order :number placed. Pay each shop in cash when they deliver.', [
                'number' => $order->order_number,
            ]),
        ]);

        return to_route('checkout.confirmation', $order);
    }

    public function confirmation(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'vendorOrders.vendor.media',
            'vendorOrders.items',
            'shippingAddress.district',
            'shippingAddress.sector',
        ]);

        return Inertia::render('storefront/confirmation', [
            'order' => [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping_fee' => $order->shipping_fee,
                'total' => $order->total,
                'payment_method' => $order->payment_method->value,
                'placed_at' => $order->placed_at,
                'shipping_address' => $this->addressProps($order->shippingAddress),
                'vendor_orders' => $order->vendorOrders
                    ->map(fn (VendorOrder $vendorOrder): array => [
                        'id' => $vendorOrder->uuid,
                        'order_number' => $vendorOrder->order_number,
                        'status' => $vendorOrder->status->value,
                        // Every total here is exactly the cash that shop collects
                        // at the door.
                        ...$this->vendorOrderTotals($vendorOrder),
                        'vendor' => [
                            ...$this->vendorCard($vendorOrder->vendor),
                            'phone' => $vendorOrder->vendor->phone,
                        ],
                        'items' => $vendorOrder->items
                            ->map(fn (OrderItem $item): array => $this->orderItemLine($item))
                            ->all(),
                    ])
                    ->all(),
            ],
        ]);
    }

    /**
     * @param  Collection<int, Address>  $addresses
     */
    private function selectedAddress(Request $request, Collection $addresses): ?Address
    {
        $requested = $request->string('address')->value();

        if ($requested !== '') {
            $match = $addresses->firstWhere('uuid', $requested);

            if ($match instanceof Address) {
                return $match;
            }
        }

        return $addresses->firstWhere('is_default', true) ?? $addresses->first();
    }

    private function findCoupon(?string $code): ?Coupon
    {
        $code = mb_trim((string) $code);

        if ($code === '') {
            return null;
        }

        return Coupon::query()->where('code', $code)->first();
    }

    /**
     * What to tell the customer about the code they typed.
     *
     * A coupon that exists but buys nothing on this cart — wrong shop, below its
     * minimum, already used up — is reported as not applicable rather than being
     * silently dropped.
     *
     * @return array<string, mixed>
     */
    private function couponProps(string $code, CheckoutQuote $quote): array
    {
        $applied = $quote->coupon instanceof Coupon;

        return [
            'code' => $code,
            'applied' => $applied,
            'discount' => $quote->discount,
            'message' => $applied
                ? __('Coupon :code applied.', ['code' => $code])
                : __('That coupon cannot be applied to this cart.'),
        ];
    }
}
