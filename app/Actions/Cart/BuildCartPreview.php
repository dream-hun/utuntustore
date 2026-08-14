<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Config;

/**
 * The flat basket the storefront's cart drawer renders.
 *
 * Deliberately not grouped by vendor: the drawer is a glance at what is in the bag,
 * and the split into one delivery and one cash payment per shop is spelled out on the
 * cart and checkout pages, where the customer is actually making that decision.
 */
final readonly class BuildCartPreview
{
    /**
     * @return array{items: array<int, array<string, mixed>>, subtotal: int, count: int, currency: string}
     */
    public function handle(?Cart $cart): array
    {
        if (! $cart instanceof Cart) {
            return [
                'items' => [],
                'subtotal' => 0,
                'count' => 0,
                'currency' => Config::string('marketplace.currency'),
            ];
        }

        $items = $cart->items()
            ->with(['product.vendor', 'product.media', 'productVariant'])
            ->get();

        $lines = $items->map(fn (CartItem $item): array => $this->line($item))->values()->all();

        return [
            'items' => $lines,
            'subtotal' => array_sum(array_column($lines, 'subtotal')),
            'count' => array_sum(array_column($lines, 'quantity')),
            'currency' => $cart->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(CartItem $item): array
    {
        $variant = $item->productVariant;
        $unitPrice = $variant->price ?? $item->product->price;
        $available = $variant->stock_quantity ?? $item->product->stock_quantity;

        return [
            'id' => $item->uuid,
            'name' => $item->product->name,
            'slug' => $item->product->slug,
            'image_url' => $item->product->getFirstMediaUrl('images', 'thumb') ?: null,
            'vendor_name' => $item->product->vendor->shop_name,
            'variant_name' => $variant?->name,
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $item->quantity,
            'max_quantity' => min($available, Config::integer('marketplace.catalog.max_cart_quantity')),
        ];
    }
}
