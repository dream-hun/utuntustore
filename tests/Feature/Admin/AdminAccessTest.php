<?php

declare(strict_types=1);

use App\Enums\SubscriptionPaymentMethod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Support\Settings;

/**
 * The admin area controls vendor approval and the platform's revenue ledger, so the
 * role gate on it is the difference between a marketplace and an open till.
 */

/**
 * @return array<int, string>
 */
function adminGetRoutes(): array
{
    return [
        route('admin.dashboard'),
        route('admin.vendors.index'),
        route('admin.subscriptions.index'),
        route('admin.categories.index'),
        route('admin.customers.index'),
        route('admin.orders.index'),
        route('admin.settings.edit'),
    ];
}

it('refuses every admin screen to a guest', function (): void {
    foreach (adminGetRoutes() as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});

it('refuses every admin screen to a customer', function (): void {
    $customer = User::factory()->customer()->create();

    foreach (adminGetRoutes() as $url) {
        $this->actingAs($customer)->get($url)->assertForbidden();
    }
});

/**
 * A vendor is a trusted party with a real stake in these screens — they would love to
 * approve themselves or record a payment they never made. They must be refused too.
 */
it('refuses every admin screen to a vendor', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $vendor->user->update(['role' => UserRole::Vendor]);

    foreach (adminGetRoutes() as $url) {
        $this->actingAs($vendor->user)->get($url)->assertForbidden();
    }
});

it('allows an admin through', function (): void {
    $admin = User::factory()->admin()->create();

    foreach (adminGetRoutes() as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});

it('refuses a suspended admin', function (): void {
    $admin = User::factory()->admin()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
});

it('stops a vendor moderating themselves into approval', function (): void {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Pending]);
    $vendor->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($vendor->user)
        ->patch(route('admin.vendors.moderate', $vendor), [
            'status' => VendorStatus::Approved->value,
        ])
        ->assertForbidden();

    expect($vendor->fresh()->status)->toBe(VendorStatus::Pending);
});

it('stops a vendor recording their own subscription payment', function (): void {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Approved]);
    $vendor->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($vendor->user)
        ->post(route('admin.subscriptions.store'), [
            'vendor' => $vendor->uuid,
            'payment_method' => SubscriptionPaymentMethod::Cash->value,
            'reference' => 'SELF-SERVE',
        ])
        ->assertForbidden();

    expect(VendorSubscription::query()->count())->toBe(0);
});

it('stops a customer changing the platform settings', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->put(route('admin.settings.update'), [
            'vendor_subscription_fee' => 1,
            'vendor_subscription_currency' => 'RWF',
            'vendor_subscription_days' => 365,
            'vendor_subscription_grace_days' => 7,
        ])
        ->assertForbidden();

    expect(resolve(Settings::class)->subscriptionFee())->not->toBe(1);
});

it('stops a customer suspending another customer', function (): void {
    $customer = User::factory()->customer()->create();
    $victim = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->patch(route('admin.customers.status', $victim), [
            'status' => UserStatus::Suspended->value,
        ])
        ->assertForbidden();

    expect($victim->fresh()->status)->toBe(UserStatus::Active);
});

it('stops a vendor deleting a category out from under the catalog', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $vendor->user->update(['role' => UserRole::Vendor]);

    $category = Category::factory()->create();

    $this->actingAs($vendor->user)
        ->delete(route('admin.categories.destroy', $category))
        ->assertForbidden();

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('stops a vendor reading another shop private detail page', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $vendor->user->update(['role' => UserRole::Vendor]);

    $rival = Vendor::factory()->sellable()->create();

    $this->actingAs($vendor->user)
        ->get(route('admin.vendors.show', $rival))
        ->assertForbidden();
});

it('stops a customer reading another customer order through the admin area', function (): void {
    $customer = User::factory()->customer()->create();
    $order = Order::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.orders.show', $order))
        ->assertForbidden();
});

it('lets an admin record a payment, which is the platform only revenue event', function (): void {
    $admin = User::factory()->admin()->create();
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Approved]);

    $this->actingAs($admin)
        ->post(route('admin.subscriptions.store'), [
            'vendor' => $vendor->uuid,
            'payment_method' => SubscriptionPaymentMethod::MobileMoney->value,
            'reference' => 'MOMO-001',
        ])
        ->assertRedirect();

    expect($vendor->fresh()->canSell())->toBeTrue()
        ->and(VendorSubscription::query()->where('status', VendorSubscriptionStatus::Active)->count())->toBe(1);
});
