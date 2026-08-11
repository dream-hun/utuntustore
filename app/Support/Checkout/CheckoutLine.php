<?php

declare(strict_types=1);

namespace App\Support\Checkout;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * A single priced line in a checkout quote.
 *
 * Prices here are re-resolved from the product, never taken from the cart row, so a
 * cart left open for a week cannot lock in a stale price.
 */
final readonly class CheckoutLine
{
    /**
     * @param  array<int, CheckoutProblem>  $problems
     */
    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public int $quantity,
        public int $unitPrice,
        public int $subtotal,
        public array $problems = [],
    ) {}

    public function name(): string
    {
        return $this->product->name;
    }

    public function variantName(): ?string
    {
        return $this->variant?->name;
    }

    public function sku(): ?string
    {
        return $this->variant->sku ?? $this->product->sku;
    }

    public function hasBlockingProblem(): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem->isBlocking()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The stock pool this line draws from — the variant's when there is one.
     */
    public function availableStock(): int
    {
        return $this->variant->stock_quantity ?? $this->product->stock_quantity;
    }
}
