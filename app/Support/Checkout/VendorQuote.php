<?php

declare(strict_types=1);

namespace App\Support\Checkout;

use App\Models\Vendor;
use App\Models\VendorDeliveryArea;

/**
 * What one vendor on a multi-vendor order will charge and collect.
 *
 * The total here is exactly what the customer will hand this vendor at the door.
 * Nothing is subtracted for the platform, because the platform takes no commission
 * and never receives the money in the first place.
 */
final readonly class VendorQuote
{
    /**
     * @param  array<int, CheckoutLine>  $lines
     * @param  array<int, CheckoutProblem>  $problems
     */
    public function __construct(
        public Vendor $vendor,
        public array $lines,
        public int $subtotal,
        public int $shippingFee,
        public int $discount,
        public int $total,
        public ?VendorDeliveryArea $deliveryArea,
        public array $problems = [],
    ) {}

    public function hasBlockingProblem(): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem->isBlocking()) {
                return true;
            }
        }

        foreach ($this->lines as $line) {
            if ($line->hasBlockingProblem()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, CheckoutProblem>
     */
    public function allProblems(): array
    {
        $problems = $this->problems;

        foreach ($this->lines as $line) {
            $problems = [...$problems, ...$line->problems];
        }

        return array_values(array_unique($problems, SORT_REGULAR));
    }

    public function estimatedDaysMin(): ?int
    {
        return $this->deliveryArea?->estimated_days_min;
    }

    public function estimatedDaysMax(): ?int
    {
        return $this->deliveryArea?->estimated_days_max;
    }
}
