<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Http\Controllers\Concerns\PresentsAddresses;
use App\Models\VendorDeliveryArea;
use App\Support\Checkout\CheckoutLine;
use App\Support\Checkout\CheckoutProblem;
use App\Support\Checkout\CheckoutQuote;
use App\Support\Checkout\VendorQuote;
use App\Support\MediaUrl;

/**
 * Serializes a CheckoutQuote for the checkout screen.
 *
 * Problems are sent down already translated via CheckoutProblem::message(), so the
 * customer is told what to fix — "this shop does not deliver to your selected
 * address" — rather than being shown a generic failure.
 */
trait PresentsCheckout
{
    use PresentsAddresses;
    use PresentsCatalog;

    /**
     * @return array<string, mixed>
     */
    protected function quoteProps(CheckoutQuote $quote): array
    {
        return [
            'vendor_quotes' => array_map(
                fn (VendorQuote $vendorQuote): array => $this->vendorQuoteProps($vendorQuote),
                array_values($quote->vendorQuotes),
            ),
            'subtotal' => $quote->subtotal,
            'discount' => $quote->discount,
            'shipping_fee' => $quote->shippingFee,
            'tax' => $quote->tax,
            'total' => $quote->total,
            'currency' => $quote->currency,
            'item_count' => $quote->itemCount(),
            'vendor_count' => $quote->vendorCount(),
            'is_placeable' => $quote->isPlaceable(),
            'problems' => $this->problemProps($quote->problems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function vendorQuoteProps(VendorQuote $vendorQuote): array
    {
        return [
            'vendor' => $this->vendorCard($vendorQuote->vendor),
            'lines' => array_map(
                fn (CheckoutLine $line): array => $this->lineProps($line),
                array_values($vendorQuote->lines),
            ),
            'subtotal' => $vendorQuote->subtotal,
            'discount' => $vendorQuote->discount,
            'shipping_fee' => $vendorQuote->shippingFee,
            'total' => $vendorQuote->total,
            'delivers' => $vendorQuote->deliveryArea instanceof VendorDeliveryArea,
            'estimated_days_min' => $vendorQuote->estimatedDaysMin(),
            'estimated_days_max' => $vendorQuote->estimatedDaysMax(),
            'problems' => $this->problemProps($vendorQuote->problems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lineProps(CheckoutLine $line): array
    {
        return [
            'id' => $line->product->uuid.':'.($line->variant->uuid ?? 'base'),
            'name' => $line->name(),
            'slug' => $line->product->slug,
            'variant_name' => $line->variantName(),
            'image_url' => MediaUrl::fromCollection($line->product, 'images', 'thumb'),
            'quantity' => $line->quantity,
            'unit_price' => $line->unitPrice,
            'subtotal' => $line->subtotal,
            'available_stock' => $line->availableStock(),
            'problems' => $this->problemProps($line->problems),
        ];
    }

    /**
     * @param  array<int, CheckoutProblem>  $problems
     * @return array<int, array<string, mixed>>
     */
    protected function problemProps(array $problems): array
    {
        return array_map(static fn (CheckoutProblem $problem): array => [
            'code' => $problem->value,
            'message' => $problem->message(),
            'blocking' => $problem->isBlocking(),
        ], array_values($problems));
    }
}
