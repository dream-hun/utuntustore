<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Session;
use Laravel\Fortify\Features;

/**
 * The security screen and the shared Inertia payload both branch on things a normal
 * request never varies: a disabled Fortify feature, and a vendor-role account that
 * owns no shop.
 */
it('sends no passkey list when the feature is switched off', function (): void {
    config(['fortify.features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::twoFactorAuthentication(),
    ]]);

    $user = User::factory()->customer()->create();

    Session::put('auth.password_confirmed_at', time());

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManagePasskeys', false)
            ->has('passkeys', 0),
        );
});

/**
 * A vendor-role account with no shop row is what an admin sees mid-onboarding, and
 * the shared payload has to survive it rather than blowing up on a null shop.
 */
it('shares no shop for a vendor account that owns none', function (): void {
    $user = User::factory()->create(['role' => UserRole::Vendor]);

    expect($user->vendor)->toBeNull();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.role', UserRole::Vendor->value)
            ->where('auth.vendor', null),
        );
});

it('shares the shop context a vendor UI needs', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $vendor->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($vendor->user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.vendor.shop_name', $vendor->shop_name)
            ->where('auth.vendor.can_sell', true),
        );
});
