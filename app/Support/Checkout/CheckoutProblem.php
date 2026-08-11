<?php

declare(strict_types=1);

namespace App\Support\Checkout;

/**
 * Every reason a cart can fail to become an order.
 *
 * These are surfaced to the customer per line and per vendor rather than as a single
 * "checkout failed", because each one has a different fix: change the address, reduce
 * a quantity, or remove a shop that has gone away.
 */
enum CheckoutProblem: string
{
    case VendorCannotSell = 'vendor_cannot_sell';
    case VendorDoesNotDeliver = 'vendor_does_not_deliver';
    case ProductUnavailable = 'product_unavailable';
    case InsufficientStock = 'insufficient_stock';
    case PriceChanged = 'price_changed';
    case EmptyCart = 'empty_cart';
    case CouponUnavailable = 'coupon_unavailable';

    public function message(): string
    {
        return match ($this) {
            self::VendorCannotSell => __('This shop is not currently accepting orders.'),
            self::VendorDoesNotDeliver => __('This shop does not deliver to your selected address.'),
            self::ProductUnavailable => __('This product is no longer available.'),
            self::InsufficientStock => __('There is not enough stock to fulfil this quantity.'),
            self::PriceChanged => __('The price of this product has changed.'),
            self::EmptyCart => __('Your cart is empty.'),
            self::CouponUnavailable => __('That coupon is no longer available.'),
        };
    }

    /**
     * Whether the problem stops the order outright, as opposed to merely warning.
     *
     * A price change is not blocking: the quote simply re-prices from the product,
     * and the customer is told what changed before they confirm.
     */
    public function isBlocking(): bool
    {
        return $this !== self::PriceChanged;
    }
}
