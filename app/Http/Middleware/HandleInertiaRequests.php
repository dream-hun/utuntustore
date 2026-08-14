<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Cart\BuildCartPreview;
use App\Models\Cart;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Cast;
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

            // Resolved lazily so the count query only runs on the pages that read it.
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

    private function cartCount(Request $request): int
    {
        $cart = $this->currentCart($request);

        if (! $cart instanceof Cart) {
            return 0;
        }

        return Cast::int($cart->items()->sum('quantity'));
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

        if ($user instanceof User) {
            return $user->cart;
        }

        return Cart::query()
            ->whereNull('user_id')
            ->where('session_id', $request->session()->getId())
            ->first();
    }
}
