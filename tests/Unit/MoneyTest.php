<?php

declare(strict_types=1);

use App\Support\Money;

/**
 * Every amount in this system is a whole number of Rwandan Francs. RWF has no minor
 * unit in practice, so there is no cents concept, and splitting an order across
 * several vendors can never produce a fraction.
 */
it('writes an amount the way a customer expects to read it', function (): void {
    expect(Money::format(320000))->toBe('320,000 FRW')
        ->and(Money::format(0))->toBe('0 FRW')
        // Anything that is not RWF keeps its own code.
        ->and(Money::format(1500, 'USD'))->toBe('1,500 USD');
});

/**
 * Rounding down is deliberate: a discount must never come out larger than its rate.
 */
it('rounds a percentage down rather than up', function (): void {
    expect(Money::percentageOf(10000, 10))->toBe(1000)
        ->and(Money::percentageOf(999, 10))->toBe(99)
        ->and(Money::percentageOf(0, 50))->toBe(0)
        ->and(Money::percentageOf(5000, 0))->toBe(0);
});

it('clamps an amount into its allowed range', function (): void {
    expect(Money::clamp(500, 0, 1000))->toBe(500)
        ->and(Money::clamp(5000, 0, 1000))->toBe(1000)
        ->and(Money::clamp(-50, 0, 1000))->toBe(0);
});
