<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Vendor;
use Illuminate\Http\Request;
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
                'vendor' => $user?->isVendor() === true
                    ? $this->vendorProps($user->vendor)
                    : null,
            ],

            // Resolved lazily so the count query only runs on the pages that read it.
            'cartCount' => fn (): int => $this->cartCount($request),

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
        $cart = $request->user()?->cart;

        if ($cart === null) {
            return 0;
        }

        return (int) $cart->items()->sum('quantity');
    }
}
