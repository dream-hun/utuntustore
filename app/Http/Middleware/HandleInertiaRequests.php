<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Cart\BuildCartPreview;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Cast;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role->value,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                ],
                // Only a vendor has a shop, and the overwhelming majority of signed-in
                // traffic is customers. Asking the role first — already loaded on the
                // user row — keeps a vendor lookup off every one of their requests.
                'vendor' => $user?->isVendor() === true ? $this->vendorProps($user->vendor) : null,
            ],

            // A plain closure is NOT lazy — Inertia resolves every one of them on every
            // request, and only skips props on a partial reload that did not ask for
            // them. So this runs on admin and vendor screens too, and it is kept to a
            // single aggregate query for that reason.
            'cartCount' => fn (): int => $this->cartCount($request),

            // Optional, so the basket is only assembled when the cart drawer actually
            // asks for it rather than on every page load that never opens the drawer.
            'cartPreview' => Inertia::optional(
                fn (): array => app(BuildCartPreview::class)->handle($this->currentCart($request)),
            ),

            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The vendor context every vendor-area screen needs.
     *
     * `canSell` is shared globally because it changes what the whole vendor UI offers:
     * an expired vendor keeps their dashboard but loses the ability to publish, and
     * the interface should say so rather than fail on submit.
     *
     * @return array<string, mixed>|null
     */
    private function vendorProps(?Vendor $vendor): ?array
    {
        if (! $vendor instanceof Vendor) {
            return null;
        }

        return [
            'id' => $vendor->uuid,
            'shop_name' => $vendor->shop_name,
            'slug' => $vendor->slug,
            'status' => $vendor->status->value,
            'subscription_status' => $vendor->subscription_status->value,
            'subscription_ends_at' => $vendor->subscription_ends_at,
            'can_sell' => $vendor->canSell(),
        ];
    }

    /**
     * The number of items in the visitor's basket, as one aggregate query.
     *
     * This is the single most-executed query in the application — it runs on every
     * request that renders any Inertia page, signed in or not. It deliberately does
     * not go through {@see currentCart()}: finding the cart row and then summing its
     * items is two round trips and hydrates a Cart model whose only use would be its
     * primary key. Selecting that key as a subquery instead does the whole thing in
     * one, and returns no rows at all for the many visitors who have never added
     * anything.
     */
    private function cartCount(Request $request): int
    {
        return Cast::int(
            CartItem::query()
                ->whereIn('cart_id', fn (QueryBuilder $query): QueryBuilder => $this->currentCartIdQuery($request, $query))
                ->sum('quantity'),
        );
    }

    /**
     * Select the id of the visitor's cart, for use as a subquery.
     *
     * carts carries two separate single-column unique indexes, `user_id` and
     * `session_id` — not one composite over the pair. Each branch filters on whichever
     * of them it owns, so both still match at most one row however many carts the
     * table holds. The guest branch's extra `whereNull('user_id')` is a correctness
     * filter rather than part of the lookup: the `session_id` index alone already
     * narrows it to a single row.
     */
    private function currentCartIdQuery(Request $request, QueryBuilder $query): QueryBuilder
    {
        $query->select('id')->from('carts');

        $user = $request->user();

        if ($user instanceof User) {
            return $query->where('user_id', $user->id);
        }

        return $query
            ->whereNull('user_id')
            ->where('session_id', $request->session()->getId());
    }

    /**
     * The visitor's existing cart, if they have one.
     *
     * Guests shop against a session-keyed cart, so reading only the user relation left
     * the header badge stuck on zero for everyone who had not signed in — the majority
     * of people filling a basket.
     *
     * This is a lookup rather than a call to ResolveCart on purpose: it runs on every
     * request, and firstOrCreate would leave a cart row behind for every visitor who
     * never adds anything, including ones who only ever see an admin screen.
     */
    private function currentCart(Request $request): ?Cart
    {
        $user = $request->user();

        return $user instanceof User
            ? $user->cart
            : Cart::query()
                ->whereNull('user_id')
                ->where('session_id', $request->session()->getId())
                ->first();
    }
}
