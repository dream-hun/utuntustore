<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Models\VendorOrder;
use App\Models\VendorSubscription;

/**
 * Suspension is the platform's only real recourse in a dispute: because it never held
 * the customer's money there is nothing to refund, and the most it can do is take the
 * shop off the storefront. Moderation must therefore leave the catalog untouched, so
 * reinstating restores the shop exactly as it was.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists vendors with their catalog and order counts', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    Product::factory()->count(2)->for($vendor)->create();
    VendorOrder::factory()->for($vendor)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.vendors.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/vendors/index')
            ->has('vendors.data', 1)
            ->where('vendors.data.0.shop_name', $vendor->shop_name)
            ->where('vendors.data.0.products_count', 2)
            ->where('vendors.data.0.orders_count', 1)
            ->where('vendors.data.0.can_sell', true)
            ->where('vendors.data.0.owner_name', $vendor->user->name),
        );
});

it('searches vendors by shop name, email and phone', function (): void {
    $target = Vendor::factory()->sellable()->create([
        'shop_name' => 'Kigali Fresh Market',
        'email' => 'kigalifresh@example.test',
        'phone' => '+250788999000',
    ]);

    Vendor::factory()->sellable()->create();

    foreach (['Kigali Fresh', 'kigalifresh@example.test', '788999000'] as $term) {
        $this->actingAs($this->admin)
            ->get(route('admin.vendors.index', ['search' => $term]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('vendors.data', 1)
                ->where('vendors.data.0.id', $target->uuid)
                ->where('filters.search', $term),
            );
    }
});

it('filters vendors by application status and by selling eligibility', function (): void {
    $pending = Vendor::factory()->create(['status' => VendorStatus::Pending]);
    $expired = Vendor::factory()->expired()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.vendors.index', ['status' => VendorStatus::Pending->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.id', $pending->uuid)
            ->where('filters.status', VendorStatus::Pending->value),
        );

    $this->actingAs($this->admin)
        ->get(route('admin.vendors.index', ['subscription_status' => SubscriptionStatus::Expired->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.id', $expired->uuid)
            ->where('filters.subscription_status', SubscriptionStatus::Expired->value),
        );
});

it('ignores filters that are not real statuses', function (): void {
    Vendor::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.vendors.index', ['status' => 'nope', 'subscription_status' => 'nope']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('vendors.data', 2));
});

it('shows one shop with its owner, payment history and recent orders', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    VendorDeliveryArea::factory()->for($vendor)->districtWide()->create();

    $subscription = VendorSubscription::factory()->for($vendor)->active()->create();

    $order = Order::factory()->create();
    $vendorOrder = VendorOrder::factory()->for($vendor)->for($order)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.vendors.show', $vendor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/vendors/show')
            ->where('vendor.shop_name', $vendor->shop_name)
            ->where('vendor.delivery_areas_count', 1)
            ->where('vendor.is_platform_owned', false)
            ->where('vendor.owner.email', $vendor->user->email)
            ->has('subscriptions', 1)
            ->where('subscriptions.0.id', $subscription->uuid)
            ->where('subscriptions.0.amount', $subscription->amount)
            ->has('recentOrders', 1)
            ->where('recentOrders.0.id', $vendorOrder->uuid)
            ->where('recentOrders.0.parent_order_number', $order->order_number),
        );
});

it('approves a pending shop and stamps the date it was let in', function (): void {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Pending, 'approved_at' => null]);

    $this->actingAs($this->admin)
        ->patch(route('admin.vendors.moderate', $vendor), ['status' => VendorStatus::Approved->value])
        ->assertRedirect();

    $vendor->refresh();

    expect($vendor->status)->toBe(VendorStatus::Approved)
        ->and($vendor->approved_at)->not->toBeNull()
        // Approval alone does not permit selling; a paid subscription is still needed.
        ->and($vendor->canSell())->toBeFalse();
});

it('keeps the original approval date when a suspended shop is reinstated', function (): void {
    $approvedAt = now()->subYear()->startOfDay();
    $vendor = Vendor::factory()->suspended()->create(['approved_at' => $approvedAt]);

    $this->actingAs($this->admin)
        ->patch(route('admin.vendors.moderate', $vendor), ['status' => VendorStatus::Approved->value])
        ->assertRedirect();

    expect($vendor->fresh()->approved_at->toDateString())->toBe($approvedAt->toDateString());
});

it('rejects an application', function (): void {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Pending]);

    $this->actingAs($this->admin)
        ->patch(route('admin.vendors.moderate', $vendor), ['status' => VendorStatus::Rejected->value])
        ->assertRedirect();

    expect($vendor->fresh()->status)->toBe(VendorStatus::Rejected);
});

it('suspends a shop without touching its catalog or subscription', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $product = Product::factory()->for($vendor)->published()->create();
    $endsAt = $vendor->subscription_ends_at;

    $this->actingAs($this->admin)
        ->patch(route('admin.vendors.moderate', $vendor), ['status' => VendorStatus::Suspended->value])
        ->assertRedirect();

    $vendor->refresh();

    expect($vendor->status)->toBe(VendorStatus::Suspended)
        ->and($vendor->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($vendor->subscription_ends_at->toDateTimeString())->toBe($endsAt->toDateTimeString())
        ->and($product->fresh()->published_at)->not->toBeNull()
        ->and($vendor->canSell())->toBeFalse();
});

it('refuses to moderate a shop back to pending', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.vendors.moderate', $vendor), ['status' => VendorStatus::Pending->value])
        ->assertSessionHasErrors('status');

    expect($vendor->fresh()->status)->toBe(VendorStatus::Approved);
});
