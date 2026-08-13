<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Support\Checkout\CheckoutLine;
use App\Support\Checkout\CheckoutProblem;
use App\Support\Checkout\CheckoutQuote;
use App\Support\Checkout\VendorQuote;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Turns a cart plus a delivery address into a fully validated, fully priced quote.
 *
 * This is the single place the checkout rules live. The checkout screen renders a
 * quote, and PlaceOrder writes one — so the customer can never be shown one set of
 * numbers and charged another.
 *
 * Order of validation follows docs/06-system-architecture.md section 8:
 * cart → vendor eligibility → address → coverage → shipping → stock → pricing → coupon.
 */
final readonly class BuildCheckoutQuote
{
    public function __construct(
        private ResolveDeliveryArea $deliveryAreas,
        private CalculateCouponDiscount $coupons,
    ) {}

    public function handle(Cart $cart, ?Address $address = null, ?Coupon $coupon = null): CheckoutQuote
    {
        $currency = Config::string('marketplace.currency');

        $items = $cart->items()
            ->with(['product.vendor', 'productVariant'])
            ->get();

        if ($items->isEmpty()) {
            return new CheckoutQuote(
                vendorQuotes: [],
                subtotal: 0,
                discount: 0,
                shippingFee: 0,
                tax: 0,
                total: 0,
                shippingAddress: $address,
                coupon: null,
                problems: [CheckoutProblem::EmptyCart],
                currency: $currency,
            );
        }

        $grouped = $items->groupBy(fn (CartItem $item): int => $item->product->vendor_id);

        // One query for every vendor's coverage rather than one per shop.
        $areas = $address instanceof Address
            ? $this->deliveryAreas->forVendors($grouped->keys()->all(), $address)
            : [];

        $vendorQuotes = [];

        foreach ($grouped as $vendorId => $vendorItems) {
            $vendorQuotes[] = $this->buildVendorQuote(
                $vendorItems->firstOrFail()->product->vendor,
                $vendorItems,
                $address,
                $areas[$vendorId] ?? null,
            );
        }

        $subtotal = array_sum(array_map(
            static fn (VendorQuote $quote): int => $quote->subtotal,
            $vendorQuotes,
        ));

        // orders.shipping_fee must equal the sum of its vendor orders' shipping fees.
        $shippingFee = array_sum(array_map(
            static fn (VendorQuote $quote): int => $quote->shippingFee,
            $vendorQuotes,
        ));

        $discount = $coupon instanceof Coupon
            ? $this->coupons->handle($coupon, $vendorQuotes)
            : 0;

        return new CheckoutQuote(
            vendorQuotes: $this->applyDiscountToVendorQuotes($vendorQuotes, $coupon, $discount),
            subtotal: $subtotal,
            discount: $discount,
            shippingFee: $shippingFee,
            tax: 0,
            total: max(0, $subtotal - $discount) + $shippingFee,
            shippingAddress: $address,
            coupon: $discount > 0 ? $coupon : null,
            currency: $currency,
        );
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    private function buildVendorQuote(
        Vendor $vendor,
        Collection $items,
        ?Address $address,
        ?VendorDeliveryArea $deliveryArea,
    ): VendorQuote {
        $problems = [];

        // Eligibility is checked at checkout time, not when the item went in the cart.
        // A vendor whose subscription lapsed mid-basket must not be able to sell.
        if (! $vendor->canSell()) {
            $problems[] = CheckoutProblem::VendorCannotSell;
        }

        if ($address instanceof Address && ! $deliveryArea instanceof VendorDeliveryArea) {
            $problems[] = CheckoutProblem::VendorDoesNotDeliver;
        }

        $lines = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $line = $this->buildLine($item);
            $lines[] = $line;
            $subtotal += $line->subtotal;
        }

        $shippingFee = $deliveryArea->delivery_fee ?? 0;

        return new VendorQuote(
            vendor: $vendor,
            lines: $lines,
            subtotal: $subtotal,
            shippingFee: $shippingFee,
            discount: 0,
            total: $subtotal + $shippingFee,
            deliveryArea: $deliveryArea,
            problems: $problems,
        );
    }

    private function buildLine(CartItem $item): CheckoutLine
    {
        $product = $item->product;
        $variant = $item->productVariant;
        $problems = [];

        if (! $this->isPurchasable($product)) {
            $problems[] = CheckoutProblem::ProductUnavailable;
        }

        if ($variant !== null && ! $variant->is_active) {
            $problems[] = CheckoutProblem::ProductUnavailable;
        }

        // Always re-price from the live product; the cart's unit_price is a display
        // snapshot and must never be what the customer is held to.
        $unitPrice = $variant->price ?? $product->price;

        if ($unitPrice !== $item->unit_price) {
            $problems[] = CheckoutProblem::PriceChanged;
        }

        $available = $variant->stock_quantity ?? $product->stock_quantity;

        if ($available < $item->quantity) {
            $problems[] = CheckoutProblem::InsufficientStock;
        }

        return new CheckoutLine(
            product: $product,
            variant: $variant,
            quantity: $item->quantity,
            unitPrice: $unitPrice,
            subtotal: $unitPrice * $item->quantity,
            problems: $problems,
        );
    }

    /**
     * A product is purchasable only if it is published; vendor eligibility is checked
     * once at the vendor level rather than repeated per line.
     */
    private function isPurchasable(Product $product): bool
    {
        return $product->status === ProductStatus::Published
            && $product->published_at !== null
            && $product->published_at->isPast();
    }

    /**
     * Spread the order-level discount across the vendors it applies to.
     *
     * This matters because every discount is funded by the vendor granting it — the
     * platform takes no commission, so it has nothing to contribute to a markdown.
     * A vendor-scoped coupon therefore reduces only that vendor's total.
     *
     * @param  array<int, VendorQuote>  $vendorQuotes
     * @return array<int, VendorQuote>
     */
    private function applyDiscountToVendorQuotes(array $vendorQuotes, ?Coupon $coupon, int $discount): array
    {
        if ($discount <= 0 || ! $coupon instanceof Coupon) {
            return $vendorQuotes;
        }

        $eligible = array_values(array_filter(
            $vendorQuotes,
            static fn (VendorQuote $quote): bool => $coupon->vendor_id === null
                || $coupon->vendor_id === $quote->vendor->id,
        ));

        $eligibleSubtotal = array_sum(array_map(
            static fn (VendorQuote $quote): int => $quote->subtotal,
            $eligible,
        ));

        // Unreachable from handle(): CalculateCouponDiscount computes the eligible
        // subtotal the same way and returns 0 when it is empty, so a discount above
        // zero guarantees one here. Kept as the divide-by-zero guard for the intdiv()
        // below, and ignored for coverage because no cart can drive it.
        if ($eligibleSubtotal <= 0) {
            // @codeCoverageIgnoreStart
            return $vendorQuotes;
            // @codeCoverageIgnoreEnd
        }

        $allocated = 0;
        $lastEligibleIndex = null;

        foreach ($vendorQuotes as $index => $quote) {
            $applies = $coupon->vendor_id === null || $coupon->vendor_id === $quote->vendor->id;

            if ($applies && $quote->subtotal > 0) {
                $lastEligibleIndex = $index;
            }
        }

        foreach ($vendorQuotes as $index => $quote) {
            $applies = $coupon->vendor_id === null || $coupon->vendor_id === $quote->vendor->id;
            if (! $applies) {
                continue;
            }

            if ($quote->subtotal <= 0) {
                continue;
            }

            $share = $index === $lastEligibleIndex
                ? $discount - $allocated
                : intdiv($discount * $quote->subtotal, $eligibleSubtotal);

            $share = Money::clamp($share, 0, $quote->subtotal);
            $allocated += $share;

            $vendorQuotes[$index] = new VendorQuote(
                vendor: $quote->vendor,
                lines: $quote->lines,
                subtotal: $quote->subtotal,
                shippingFee: $quote->shippingFee,
                discount: $share,
                total: $quote->subtotal - $share + $quote->shippingFee,
                deliveryArea: $quote->deliveryArea,
                problems: $quote->problems,
            );
        }

        return $vendorQuotes;
    }
}
