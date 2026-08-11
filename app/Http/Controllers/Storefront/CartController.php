<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ResolveCart;
use App\Actions\Cart\UpdateCartItemQuantity;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\PresentsCatalog;
use App\Http\Requests\Storefront\AddToCartRequest;
use App\Http\Requests\Storefront\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Cast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cart, grouped by vendor.
 *
 * Grouping is not cosmetic: this is a marketplace, so a basket spanning three shops
 * becomes three separate deliveries and three separate cash payments at the door.
 * A customer who does not see that before checkout is being misled.
 */
final class CartController extends Controller
{
    use PresentsCatalog;

    public function __construct(private readonly ResolveCart $carts) {}

    public function index(Request $request): Response
    {
        $cart = $this->carts->handle($request);

        $items = $cart->items()
            ->with(['product.vendor.media', 'product.media', 'productVariant'])
            ->get();

        $groups = $this->groupByVendor($items);

        return Inertia::render('storefront/cart', [
            'groups' => $groups,
            'subtotal' => array_sum(array_column($groups, 'subtotal')),
            'itemCount' => Cast::int($items->sum('quantity')),
            'currency' => $cart->currency,
        ]);
    }

    public function store(AddToCartRequest $request, AddToCart $addToCart): RedirectResponse
    {
        // Sellable, not merely existing: a lapsed vendor's product cannot be put in
        // a basket even by posting its id directly.
        $product = Product::query()
            ->sellable()
            ->where('uuid', $request->string('product')->toString())
            ->firstOrFail();

        $variant = $this->resolveVariant(
            $product,
            $request->filled('variant') ? $request->string('variant')->toString() : null,
        );

        $cart = $this->carts->handle($request);

        try {
            $addToCart->handle($cart, $product, $request->integer('quantity'), $variant);
        } catch (CheckoutException $checkoutException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $checkoutException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':product added to your cart.', ['product' => $product->name]),
        ]);

        return back();
    }

    public function update(UpdateCartItemRequest $request, CartItem $item, UpdateCartItemQuantity $updateQuantity): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $quantity = $request->integer('quantity');

        try {
            $updateQuantity->handle($item, $quantity);
        } catch (CheckoutException $checkoutException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $checkoutException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $quantity === 0 ? __('Item removed from your cart.') : __('Cart updated.'),
        ]);

        return back();
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $item->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removed from your cart.')]);

        return back();
    }

    /**
     * A cart line may only ever be touched through the cart it belongs to, which is
     * what stops one visitor editing another's basket by guessing a uuid.
     */
    private function authorizeItem(Request $request, CartItem $item): void
    {
        abort_unless($item->cart_id === $this->carts->handle($request)->id, 403);
    }

    private function resolveVariant(Product $product, ?string $uuid): ?ProductVariant
    {
        if ($uuid === null) {
            return null;
        }

        return $product->variants()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function groupByVendor(Collection $items): array
    {
        return $items
            ->groupBy(fn (CartItem $item): int => $item->product->vendor_id)
            ->map(function (Collection $vendorItems): array {
                // groupBy never yields an empty group, so this line always exists.
                $first = $vendorItems->firstOrFail();
                $vendor = $first->product->vendor;

                return [
                    'vendor' => $this->vendorCard($vendor),
                    'items' => $vendorItems->map(fn (CartItem $item): array => $this->cartLine($item))->values()->all(),
                    'subtotal' => $vendorItems->sum(
                        fn (CartItem $item): int => ($item->productVariant->price ?? $item->product->price) * $item->quantity,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function cartLine(CartItem $item): array
    {
        $variant = $item->productVariant;
        $unitPrice = $variant->price ?? $item->product->price;
        $available = $variant->stock_quantity ?? $item->product->stock_quantity;

        return [
            'id' => $item->uuid,
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $item->quantity,
            'available_stock' => $available,
            'max_quantity' => min($available, Config::integer('marketplace.catalog.max_cart_quantity')),
            'product' => [
                'id' => $item->product->uuid,
                'name' => $item->product->name,
                'slug' => $item->product->slug,
                'image_url' => $item->product->getFirstMediaUrl('images', 'thumb') ?: null,
            ],
            'variant' => $variant === null ? null : [
                'id' => $variant->uuid,
                'name' => $variant->name,
            ],
        ];
    }
}
