<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Finds or creates the cart for the current visitor.
 *
 * Guests get a session-keyed cart so they can shop before registering; on login the
 * guest cart is merged into the account's cart rather than discarded, because losing
 * a basket at the sign-in step is one of the most expensive things a shop can do.
 */
final readonly class ResolveCart
{
    public function handle(Request $request): Cart
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $this->forUser($user, $request->session()->getId());
        }

        return Cart::query()->firstOrCreate(
            ['session_id' => $request->session()->getId(), 'user_id' => null],
            ['currency' => config('marketplace.currency')],
        );
    }

    private function forUser(User $user, string $sessionId): Cart
    {
        $cart = Cart::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => config('marketplace.currency')],
        );

        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where('session_id', $sessionId)
            ->first();

        if ($guestCart instanceof Cart && $guestCart->id !== $cart->id) {
            $this->merge($guestCart, $cart);
        }

        return $cart;
    }

    /**
     * Fold a guest cart into the account cart.
     *
     * Quantities are summed where the same product appears in both, which matches what
     * a shopper expects after adding the same thing twice in two sessions.
     */
    private function merge(Cart $guestCart, Cart $cart): void
    {
        DB::transaction(function () use ($guestCart, $cart): void {
            $existing = $cart->items()->get()->keyBy(
                fn ($item): string => $item->product_id.':'.($item->product_variant_id ?? 'null'),
            );

            foreach ($guestCart->items()->get() as $item) {
                $key = $item->product_id.':'.($item->product_variant_id ?? 'null');
                $match = $existing->get($key);

                if ($match === null) {
                    $item->cart_id = $cart->id;
                    $item->save();

                    continue;
                }

                $match->quantity = min(
                    $match->quantity + $item->quantity,
                    Config::integer('marketplace.catalog.max_cart_quantity'),
                );
                $match->save();

                $item->delete();
            }

            $guestCart->delete();
        });
    }
}
