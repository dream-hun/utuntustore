<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Checkout\CheckoutProblem;
use App\Support\Checkout\CheckoutQuote;
use RuntimeException;

/**
 * Thrown when a cart cannot legally become an order.
 *
 * Carries the specific problems so the checkout screen can tell the customer what to
 * fix rather than showing a generic failure.
 */
final class CheckoutException extends RuntimeException
{
    /**
     * @param  array<int, CheckoutProblem>  $problems
     */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }

    public static function notPlaceable(CheckoutQuote $quote): self
    {
        $problems = $quote->blockingProblems();

        $message = $problems === []
            ? __('This order cannot be placed.')
            : $problems[0]->message();

        return new self($message, $problems);
    }

    /**
     * Raised when stock ran out between validating the quote and locking the rows.
     */
    public static function stockChanged(string $productName): self
    {
        return new self(
            __(':product sold out while you were checking out.', ['product' => $productName]),
            [CheckoutProblem::InsufficientStock],
        );
    }

    /**
     * Raised when a coupon's last redemption was taken by someone else mid-checkout.
     *
     * The whole order fails rather than quietly repricing: every total the customer
     * was shown was calculated with the discount in it.
     */
    public static function couponUnavailable(): self
    {
        return new self(
            __('That coupon was fully redeemed while you were checking out.'),
            [CheckoutProblem::CouponUnavailable],
        );
    }
}
