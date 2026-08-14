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

        $eligibleSubtotal = 0;

        foreach ($vendorQuotes as $quote) {
            if ($this->couponAppliesTo($coupon, $quote)) {
                $eligibleSubtotal += $quote->subtotal;
            }
        }

        // Unreachable from handle(): CalculateCouponDiscount computes the eligible
        // subtotal the same way and returns 0 when it is empty, so a discount above
        // zero guarantees one here. Kept as the divide-by-zero guard for the intdiv()
        // below, and ignored for coverage because no cart can drive it.
        if ($eligibleSubtotal <= 0) {
            // @codeCoverageIgnoreStart
            return $vendorQuotes;
            // @codeCoverageIgnoreEnd
        }

        $shares = $this->apportionDiscount($vendorQuotes, $coupon, $discount, $eligibleSubtotal);

        foreach ($shares as $index => $share) {
            $quote = $vendorQuotes[$index];

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

    /**
     * Split a discount across the shops funding it, to the exact franc.
     *
     * Largest-remainder apportionment: give every shop the whole-franc part of its
     * proportional share, then hand the francs lost to rounding — at most one per shop —
     * to whichever shops were rounded down hardest.
     *
     * The leftover is spread rather than dumped on one shop because no single shop is
     * guaranteed to have room for it. The cheapest shop in a basket can be owed less
     * than the accumulated rounding when a coupon covers nearly the whole order, and
     * capping its share there would quietly destroy the difference, leaving the order
     * discounted by more than the shops gave up. Spreading always fits: the shops'
     * combined room is the eligible subtotal, which is never less than the discount.
     *
     * @param  array<int, VendorQuote>  $vendorQuotes
     * @return array<int, int> Discount per vendor quote, keyed by its index.
     */
    private function apportionDiscount(array $vendorQuotes, Coupon $coupon, int $discount, int $eligibleSubtotal): array
    {
        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($vendorQuotes as $index => $quote) {
            if (! $this->couponAppliesTo($coupon, $quote) || $quote->subtotal <= 0) {
                continue;
            }

            $exact = $discount * $quote->subtotal;

            $shares[$index] = intdiv($exact, $eligibleSubtotal);
            $remainders[$index] = $exact % $eligibleSubtotal;
            $allocated += $shares[$index];
        }

        // Hardest-rounded first, then lowest index, so an identical basket always
        // splits identically — vendor takings have to be reproducible.
        $byRemainder = array_keys($remainders);

        usort(
            $byRemainder,
            static fn (int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b],
        );

        $leftover = $discount - $allocated;

        foreach ($byRemainder as $index) {
            if ($leftover <= 0) {
                break;
            }

            $franc = min($vendorQuotes[$index]->subtotal - $shares[$index], $leftover);

            $shares[$index] += $franc;
            $leftover -= $franc;
        }

        return $shares;
    }

    /**
     * A vendor-scoped coupon funds only its own shop; an unscoped one funds them all.
     */
    private function couponAppliesTo(Coupon $coupon, VendorQuote $quote): bool
    {
        return $coupon->vendor_id === null || $coupon->vendor_id === $quote->vendor->id;
    }
}
