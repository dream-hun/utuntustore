<?php

declare(strict_types=1);

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ResolveCart;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;

/**
 * A visitor has exactly one cart. Losing a basket — or splitting it silently across
 * two rows — is one of the most expensive failures a shop can have, so the rule is
 * enforced by the database rather than by the code that happens to read it.
 */

/**
 * A request carrying a real session, as ResolveCart expects to receive.
 *
 * Session ids have to look like session ids: the session store discards anything
 * that is not 40 alphanumeric characters and issues a fresh id instead, which would
 * quietly give every request in a test its own cart.
 */
function sessionId(string $seed): string
{
    return mb_substr(Str::padRight($seed, 40, 'abcdefghijklmnopqrstuvwxyz0123456789'), 0, 40);
}

function requestWithSession(string $sessionId, ?User $user = null): Request
{
    $session = new Store('testing', new ArraySessionHandler(120), $sessionId);
    $session->start();

    $request = Request::create('/cart');
    $request->setLaravelSession($session);

    if ($user instanceof User) {
        $request->setUserResolver(static fn (): User => $user);
    }

    return $request;
}

it('gives a guest one cart per session, however many times it is resolved', function (): void {
    $carts = resolve(ResolveCart::class);

    $first = $carts->handle(requestWithSession(sessionId('one')));
    $second = $carts->handle(requestWithSession(sessionId('one')));

    expect($second->id)->toBe($first->id)
        ->and(Cart::query()->count())->toBe(1);

    // A different visitor is a different basket.
    $carts->handle(requestWithSession(sessionId('two')));

    expect(Cart::query()->count())->toBe(2);
});

it('gives a signed-in customer one cart, however many times it is resolved', function (): void {
    $customer = User::factory()->customer()->create();
    $carts = resolve(ResolveCart::class);

    $first = $carts->handle(requestWithSession(sessionId('one'), $customer));
    $second = $carts->handle(requestWithSession(sessionId('two'), $customer));

    expect($second->id)->toBe($first->id)
        ->and(Cart::query()->count())->toBe(1);
});

it('folds a guest basket into the account cart on sign-in rather than dropping it', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $product = Product::factory()->for($vendor)->published()->create(['stock_quantity' => 10]);

    $carts = resolve(ResolveCart::class);

    $guestCart = $carts->handle(requestWithSession(sessionId('one')));
    resolve(AddToCart::class)->handle($guestCart, $product, 2);

    $customer = User::factory()->customer()->create();

    $cart = $carts->handle(requestWithSession(sessionId('one'), $customer));

    expect($cart->user_id)->toBe($customer->id)
        ->and($cart->items()->sum('quantity'))->toBe(2)
        // The guest row is gone, not merely detached.
        ->and(Cart::query()->count())->toBe(1);
});

it('refuses a second cart for the same customer at the database level', function (): void {
    $customer = User::factory()->customer()->create();

    Cart::factory()->for($customer)->create();

    // ResolveCart find-or-creates, so two simultaneous requests can both find
    // nothing and both insert. Only the constraint can stop the second one.
    expect(fn (): Cart => Cart::factory()->for($customer)->create())
        ->toThrow(QueryException::class);
});

it('refuses a second cart for the same guest session at the database level', function (): void {
    Cart::factory()->guest()->create(['session_id' => sessionId('one')]);

    expect(fn (): Cart => Cart::factory()->guest()->create(['session_id' => sessionId('one')]))
        ->toThrow(QueryException::class);
});

it('still allows many guest carts and many account carts side by side', function (): void {
    Cart::factory()->guest()->count(3)->create();
    Cart::factory()->count(3)->create();

    // Both columns are nullable and NULLs are exempt from a unique index, so guests
    // do not collide with each other on user_id, nor accounts on session_id.
    expect(Cart::query()->count())->toBe(6);
});
