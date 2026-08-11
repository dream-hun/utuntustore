<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Enums\OrderPaymentMethod;
use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorOrder;
use App\Support\Checkout\CheckoutLine;
use App\Support\Checkout\CheckoutQuote;
use App\Support\Checkout\VendorQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes a validated quote to the database as an order, atomically.
 *
 * There is deliberately no commission step and no financial record beyond the order
 * itself. A vendor order's total is simply what the customer will hand that vendor at
 * the door — the platform records the agreement and never touches the money.
 *
 * Everything happens in one transaction: a partial order (items with no vendor order,
 * stock taken for an order that was never written) would be far worse than a failure.
 */
final readonly class PlaceOrder
{
    /**
     * @throws CheckoutException When the cart cannot legally become an order.
     */
    public function handle(User $user, Cart $cart, CheckoutQuote $quote): Order
    {
        $shippingAddress = $quote->shippingAddress;

        // isPlaceable() already requires an address; naming it here carries that
        // guarantee into createOrder(), which cannot re-derive it on its own.
        if (! $quote->isPlaceable() || ! $shippingAddress instanceof Address) {
            throw CheckoutException::notPlaceable($quote);
        }

        return DB::transaction(function () use ($user, $cart, $quote, $shippingAddress): Order {
            // Re-read stock inside the transaction with a row lock. Without this, two
            // customers checking out the last item simultaneously would both pass
            // validation and drive stock negative.
            $this->lockAndVerifyStock($quote);

            $order = $this->createOrder($user, $quote, $shippingAddress);

            foreach (array_values($quote->vendorQuotes) as $index => $vendorQuote) {
                $this->createVendorOrder($order, $vendorQuote, $index + 1);
            }

            if ($quote->coupon instanceof Coupon && $quote->discount > 0) {
                $this->recordCouponUsage($order, $user, $quote, $quote->coupon);
            }

            $cart->items()->delete();

            return $order;
        });
    }

    /**
     * Lock every product and variant in the order, then re-check availability.
     *
     * Locking in a deterministic order (by primary key) keeps two concurrent checkouts
     * that share products from deadlocking against each other.
     */
    private function lockAndVerifyStock(CheckoutQuote $quote): void
    {
        $productIds = [];
        $variantIds = [];

        foreach ($quote->vendorQuotes as $vendorQuote) {
            foreach ($vendorQuote->lines as $line) {
                if ($line->variant instanceof ProductVariant) {
                    $variantIds[] = $line->variant->id;
                } else {
                    $productIds[] = $line->product->id;
                }
            }
        }

        sort($productIds);
        sort($variantIds);

        $products = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', array_unique($productIds))->lockForUpdate()->get()->keyBy('id');

        $variants = $variantIds === []
            ? collect()
            : ProductVariant::query()->whereIn('id', array_unique($variantIds))->lockForUpdate()->get()->keyBy('id');

        $required = [];

        foreach ($quote->vendorQuotes as $vendorQuote) {
            foreach ($vendorQuote->lines as $line) {
                $key = $line->variant instanceof ProductVariant
                    ? 'variant:'.$line->variant->id
                    : 'product:'.$line->product->id;

                $required[$key] = ($required[$key] ?? 0) + $line->quantity;
            }
        }

        foreach ($required as $key => $quantity) {
            [$type, $id] = explode(':', $key);

            $model = $type === 'variant' ? $variants->get((int) $id) : $products->get((int) $id);

            // A row that vanished between quoting and locking is as much a stock
            // change as one that ran out, and the customer sees the same message.
            if (! $model instanceof Product && ! $model instanceof ProductVariant) {
                throw CheckoutException::stockChanged(__('An item'));
            }

            if ($model->stock_quantity < $quantity) {
                throw CheckoutException::stockChanged($model->name);
            }
        }

        // Decrement only after every line has been verified, so a failure late in the
        // list never leaves earlier items already deducted.
        foreach ($required as $key => $quantity) {
            [$type, $id] = explode(':', $key);

            $type === 'variant'
                ? ProductVariant::query()->whereKey((int) $id)->decrement('stock_quantity', $quantity)
                : Product::query()->whereKey((int) $id)->decrement('stock_quantity', $quantity);
        }
    }

    private function createOrder(User $user, CheckoutQuote $quote, Address $shippingAddress): Order
    {
        $order = new Order;

        $order->user_id = $user->id;
        $order->order_number = $this->generateOrderNumber();
        $order->status = OrderStatus::Pending;
        $order->currency = $quote->currency;
        $order->subtotal = $quote->subtotal;
        $order->discount = $quote->discount;
        $order->shipping_fee = $quote->shippingFee;
        $order->tax = $quote->tax;
        $order->total = $quote->total;
        $order->payment_method = OrderPaymentMethod::CashOnDelivery;
        $order->shipping_address_id = $shippingAddress->id;
        $order->billing_address_id = $shippingAddress->id;
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    private function createVendorOrder(Order $order, VendorQuote $vendorQuote, int $sequence): void
    {
        $vendorOrder = new VendorOrder;

        $vendorOrder->order_id = $order->id;
        $vendorOrder->vendor_id = $vendorQuote->vendor->id;

        // Derived from the parent order number so a customer holding one reference can
        // be matched to the right vendor's delivery over the phone.
        $vendorOrder->order_number = $order->order_number.'-'.mb_str_pad(
            (string) $sequence,
            2,
            '0',
            STR_PAD_LEFT,
        );
        $vendorOrder->subtotal = $vendorQuote->subtotal;
        $vendorOrder->discount = $vendorQuote->discount;

        // The fee resolved from this vendor's delivery areas is copied here, exactly
        // like product prices are copied onto order items. A vendor changing their
        // fees later must never alter a historical order.
        $vendorOrder->shipping_fee = $vendorQuote->shippingFee;
        $vendorOrder->tax = 0;
        $vendorOrder->total = $vendorQuote->total;
        $vendorOrder->status = OrderStatus::Pending;
        $vendorOrder->save();

        foreach ($vendorQuote->lines as $line) {
            $this->createOrderItem($order, $vendorOrder, $line);
        }
    }

    private function createOrderItem(Order $order, VendorOrder $vendorOrder, CheckoutLine $line): void
    {
        $item = new OrderItem;

        $item->order_id = $order->id;
        $item->vendor_order_id = $vendorOrder->id;
        $item->product_id = $line->product->id;
        $item->product_variant_id = $line->variant?->id;

        // Snapshotted so editing or deleting a product can never rewrite what the
        // customer actually agreed to buy.
        $item->product_name = $line->name();
        $item->variant_name = $line->variantName();
        $item->sku = $line->sku();
        $item->unit_price = $line->unitPrice;
        $item->quantity = $line->quantity;
        $item->subtotal = $line->subtotal;
        $item->save();
    }

    /**
     * Claim one redemption of the coupon, then record who used it.
     *
     * The claim is the increment itself: the redeemable condition is part of the
     * UPDATE, so the database decides who gets the last use of a limited coupon.
     * Checking first and incrementing after would let two simultaneous checkouts
     * both pass the check and push used_count past usage_limit — the same race
     * lockAndVerifyStock() exists to prevent for stock.
     *
     * @throws CheckoutException When the coupon was exhausted mid-checkout.
     */
    private function recordCouponUsage(Order $order, User $user, CheckoutQuote $quote, Coupon $coupon): void
    {
        $claimed = Coupon::query()
            ->whereKey($coupon->id)
            ->redeemable()
            ->increment('used_count');

        if ($claimed === 0) {
            throw CheckoutException::couponUnavailable();
        }

        $usage = new CouponUsage;

        $usage->coupon_id = $coupon->id;
        $usage->user_id = $user->id;
        $usage->order_id = $order->id;
        $usage->discount_amount = $quote->discount;
        $usage->save();
    }

    /**
     * Order numbers are human-quotable over the phone, which is how most delivery
     * problems in this market actually get resolved.
     */
    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
