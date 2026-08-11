<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Support\Checkout\VendorQuote;
use App\Support\Money;

/**
 * Works out what a coupon is actually worth against a set of vendor quotes.
 *
 * A vendor-scoped coupon only ever discounts that vendor's items. Since the platform
 * takes no commission, a discount is money out of the granting vendor's pocket — so
 * one shop's promotion must never reduce another shop's takings.
 */
final readonly class CalculateCouponDiscount
{
    /**
     * @param  array<int, VendorQuote>  $vendorQuotes
     */
    public function handle(Coupon $coupon, array $vendorQuotes): int
    {
        if (! $coupon->isRedeemable()) {
            return 0;
        }

        $eligibleSubtotal = 0;

        foreach ($vendorQuotes as $quote) {
            if ($coupon->vendor_id === null || $coupon->vendor_id === $quote->vendor->id) {
                $eligibleSubtotal += $quote->subtotal;
            }
        }

        if ($eligibleSubtotal <= 0 || $eligibleSubtotal < $coupon->minimum_order_amount) {
            return 0;
        }

        $discount = match ($coupon->type) {
            CouponType::Percentage => Money::percentageOf($eligibleSubtotal, (int) $coupon->value),
            CouponType::Fixed => (int) $coupon->value,
        };

        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, (int) $coupon->maximum_discount);
        }

        // A discount can never exceed what it is discounting, and never makes the
        // platform or a vendor owe the customer money.
        return Money::clamp($discount, 0, $eligibleSubtotal);
    }
}
