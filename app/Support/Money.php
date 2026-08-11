<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whole-franc money helpers.
 *
 * Every amount in this system is an integer number of Rwandan Francs. RWF has no
 * minor unit in practice, so there is no cents concept to convert to or from, and
 * splitting an order across several vendors can never produce a fraction.
 */
final class Money
{
    /**
     * Format an amount for display, e.g. 320000 => "320,000 FRW".
     *
     * RWF is written locally as FRW, which is what customers expect to see.
     */
    public static function format(int $amount, string $currency = 'RWF'): string
    {
        $suffix = $currency === 'RWF' ? 'FRW' : $currency;

        return number_format($amount).' '.$suffix;
    }

    /**
     * Apply a percentage discount, rounding down so a discount never exceeds its rate.
     */
    public static function percentageOf(int $amount, int $percentage): int
    {
        return intdiv($amount * $percentage, 100);
    }

    /**
     * Clamp an amount to a range, used to keep discounts from exceeding a subtotal.
     */
    public static function clamp(int $amount, int $min, int $max): int
    {
        return max($min, min($amount, $max));
    }
}
