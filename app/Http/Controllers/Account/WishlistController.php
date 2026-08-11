<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Account\AddToWishlist;
use App\Actions\Account\MoveWishlistItemToCart;
use App\Actions\Account\RemoveFromWishlist;
use App\Actions\Account\ResolveWishlist;
use App\Actions\Cart\ResolveCart;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreWishlistItemRequest;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products the customer saved for later.
 *
 * A saved product is not a reservation: it can go out of stock, or its shop's
 * subscription can lapse, between saving and buying. The list therefore shows live
 * availability, and moving an item to the cart re-checks it through AddToCart.
 */
final class WishlistController extends Controller
{
    public function __construct(private readonly ResolveWishlist $resolveWishlist) {}

    public function index(Request $request): Response
    {
        $wishlist = $this->resolveWishlist->handle($this->currentUser($request));

        $items = $wishlist->items()
            ->with(['product.vendor', 'product.media'])
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (WishlistItem $item): array => [
                'id' => $item->uuid,
                'saved_at' => $item->created_at,
                'product' => [
                    'id' => $item->product->uuid,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'price' => $item->product->price,
                    'compare_at_price' => $item->product->compare_at_price,
                    'currency' => $item->product->currency,
                    'primary_image_url' => $item->product->getFirstMediaUrl('images', 'thumb') ?: null,
                    'in_stock' => $item->product->isInStock(),
                    'vendor' => [
                        'id' => $item->product->vendor->uuid,
                        'shop_name' => $item->product->vendor->shop_name,
                        'slug' => $item->product->vendor->slug,
                        'can_sell' => $item->product->vendor->canSell(),
                    ],
                ],
            ]);

        return Inertia::render('account/wishlist/index', [
            'items' => $items,
        ]);
    }

    public function store(StoreWishlistItemRequest $request, AddToWishlist $addToWishlist): RedirectResponse
    {
        $product = Product::query()->where('uuid', $request->string('product')->toString())->firstOrFail();

        $addToWishlist->handle($this->currentUser($request), $product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved to your wishlist.')]);

        return back();
    }

    public function destroy(Request $request, Product $product, RemoveFromWishlist $removeFromWishlist): RedirectResponse
    {
        $removeFromWishlist->handle($this->currentUser($request), $product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed from your wishlist.')]);

        return back();
    }

    public function moveToCart(
        Request $request,
        Product $product,
        ResolveCart $resolveCart,
        MoveWishlistItemToCart $moveWishlistItemToCart,
    ): RedirectResponse {
        try {
            $moveWishlistItemToCart->handle($this->currentUser($request), $resolveCart->handle($request), $product);
        } catch (CheckoutException $checkoutException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $checkoutException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Moved to your cart.')]);

        return back();
    }
}
