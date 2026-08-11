<?php

declare(strict_types=1);

namespace App\Support\Checkout;

use App\Models\Address;
use App\Models\Coupon;

/**
 * A fully priced, fully validated view of what a cart would become if ordered now.
 *
 * The same quote drives the checkout screen and the order that gets written, so what
 * the customer confirms and what is persisted can never drift apart. It is rebuilt on
 * every request rather than stored — stale pricing is the one thing a marketplace
 * checkout must not do.
 */
final readonly class CheckoutQuote
{
    /**
     * @param  array<int, VendorQuote>  $vendorQuotes
     * @param  array<int, CheckoutProblem>  $problems
     */
    public function __construct(
        public array $vendorQuotes,
        public int $subtotal,
        public int $discount,
        public int $shippingFee,
        public int $tax,
        public int $total,
        public ?Address $shippingAddress,
        public ?Coupon $coupon,
        public array $problems = [],
        public string $currency = 'RWF',
    ) {}

    /**
     * Whether this quote can legally become an order.
     *
     * An address is mandatory: delivery coverage is what decides whether each vendor
     * can serve this customer at all, and it cannot be resolved without one.
     */
    public function isPlaceable(): bool
    {
        if ($this->vendorQuotes === []) {
            return false;
        }

        if (! $this->shippingAddress instanceof Address) {
            return false;
        }

        foreach ($this->problems as $problem) {
            if ($problem->isBlocking()) {
                return false;
            }
        }

        foreach ($this->vendorQuotes as $vendorQuote) {
            if ($vendorQuote->hasBlockingProblem()) {
                return false;
            }
        }

        return true;
    }

    public function itemCount(): int
    {
        $count = 0;

        foreach ($this->vendorQuotes as $vendorQuote) {
            foreach ($vendorQuote->lines as $line) {
                $count += $line->quantity;
            }
        }

        return $count;
    }

    public function vendorCount(): int
    {
        return count($this->vendorQuotes);
    }

    /**
     * @return array<int, CheckoutProblem>
     */
    public function blockingProblems(): array
    {
        $problems = array_filter(
            $this->problems,
            static fn (CheckoutProblem $problem): bool => $problem->isBlocking(),
        );

        foreach ($this->vendorQuotes as $vendorQuote) {
            foreach ($vendorQuote->allProblems() as $problem) {
                if ($problem->isBlocking()) {
                    $problems[] = $problem;
                }
            }
        }

        return array_values(array_unique($problems, SORT_REGULAR));
    }
}
