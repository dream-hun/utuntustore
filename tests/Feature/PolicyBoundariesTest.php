<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorSubscription;

/**
 * The policy layer read straight, rather than through the routes that happen to use
 * it. Several of these answers are only reachable from an admin — who has no route to
 * a vendor's catalog — and from callers outside HTTP such as jobs and commands.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->customer()->create();

    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);

    $this->rival = Vendor::factory()->sellable()->create();
    $this->rivalUser = $this->rival->user;
    $this->rivalUser->update(['role' => UserRole::Vendor]);
});

it('keeps an address private to the customer who owns it', function (): void {
    $mine = Address::factory()->for($this->customer)->create();
    $theirs = Address::factory()->create();

    expect($this->customer->can('view', $mine))->toBeTrue()
        ->and($this->customer->can('view', $theirs))->toBeFalse()
        // A vendor sees delivery details only as a snapshot on their own vendor order.
        ->and($this->vendorUser->can('view', $mine))->toBeFalse();
});

it('lets a vendor and an admin into the catalog, and nobody else', function (): void {
    expect($this->vendorUser->can('viewAny', Product::class))->toBeTrue()
        ->and($this->admin->can('viewAny', Product::class))->toBeTrue()
        ->and($this->customer->can('viewAny', Product::class))->toBeFalse()
        // A vendor stocks their own shop; an admin adds to a shop on its behalf, which
        // is why creating is the one write an admin shares with them.
        ->and($this->vendorUser->can('create', Product::class))->toBeTrue()
        ->and($this->admin->can('create', Product::class))->toBeTrue()
        ->and($this->customer->can('create', Product::class))->toBeFalse();
});

it('shows a product to its owner and to an admin only', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    expect($this->vendorUser->can('view', $product))->toBeTrue()
        ->and($this->admin->can('view', $product))->toBeTrue()
        ->and($this->rivalUser->can('view', $product))->toBeFalse()
        ->and($this->customer->can('view', $product))->toBeFalse();
});

/**
 * An admin curates the catalog: they may edit and remove any shop's product, which is
 * how a platform operator fixes a listing without waiting on the vendor. A rival vendor
 * still may not touch it.
 */
it('lets an admin curate any shop product but never a rival vendor', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    expect($this->admin->can('update', $product))->toBeTrue()
        ->and($this->admin->can('delete', $product))->toBeTrue()
        ->and($this->rivalUser->can('update', $product))->toBeFalse()
        ->and($this->rivalUser->can('delete', $product))->toBeFalse()
        ->and($this->customer->can('update', $product))->toBeFalse()
        ->and($this->customer->can('delete', $product))->toBeFalse();
});

/**
 * Ownership and selling eligibility are separate: an admin can look and curate, but
 * publishing stays with the shop that has a live subscription.
 */
it('separates owning a product from being allowed to publish it', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    expect($this->vendorUser->can('publish', $product))->toBeTrue()
        ->and($this->admin->can('publish', $product))->toBeFalse();

    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);
    $theirProduct = Product::factory()->for($expired)->create();

    expect($expired->user->can('update', $theirProduct))->toBeTrue()
        ->and($expired->user->can('publish', $theirProduct))->toBeFalse();
});

it('gives a variant the same owner as its product', function (): void {
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for($this->vendor)->create())
        ->create();

    expect($this->vendorUser->can('view', $variant))->toBeTrue()
        ->and($this->vendorUser->can('update', $variant))->toBeTrue()
        ->and($this->vendorUser->can('delete', $variant))->toBeTrue()
        ->and($this->admin->can('view', $variant))->toBeTrue()
        ->and($this->admin->can('update', $variant))->toBeFalse()
        ->and($this->rivalUser->can('view', $variant))->toBeFalse()
        ->and($this->rivalUser->can('delete', $variant))->toBeFalse()
        ->and($this->customer->can('view', $variant))->toBeFalse();
});

it('lets an admin read any vendor order but advance none of them', function (): void {
    $vendorOrder = VendorOrder::factory()->for($this->vendor)->create();

    expect($this->admin->can('viewAny', VendorOrder::class))->toBeTrue()
        ->and($this->admin->can('view', $vendorOrder))->toBeTrue()
        // Delivery is a vendor-reported fact, so nobody else can report it.
        ->and($this->admin->can('update', $vendorOrder))->toBeFalse()
        ->and($this->customer->can('viewAny', VendorOrder::class))->toBeFalse();
});

it('lets a vendor read their own payment history and nobody else write it', function (): void {
    $mine = VendorSubscription::factory()->for($this->vendor)->active()->create();
    $theirs = VendorSubscription::factory()->for($this->rival)->active()->create();

    expect($this->vendorUser->can('view', $mine))->toBeTrue()
        ->and($this->vendorUser->can('view', $theirs))->toBeFalse()
        ->and($this->admin->can('view', $theirs))->toBeTrue()
        ->and($this->customer->can('view', $mine))->toBeFalse();

    // Payment happens off-platform; only an admin who confirmed it can record one.
    expect($this->admin->can('create', VendorSubscription::class))->toBeTrue()
        ->and($this->vendorUser->can('create', VendorSubscription::class))->toBeFalse()
        ->and($this->admin->can('update', VendorSubscription::class))->toBeTrue()
        ->and($this->vendorUser->can('update', VendorSubscription::class))->toBeFalse()
        ->and($this->admin->can('cancel', $mine))->toBeTrue()
        ->and($this->vendorUser->can('cancel', $mine))->toBeFalse();
});

it('lets a vendor manage their own shop and delivery, and an admin only the shop', function (): void {
    expect($this->vendorUser->can('update', $this->vendor))->toBeTrue()
        ->and($this->admin->can('update', $this->vendor))->toBeTrue()
        ->and($this->rivalUser->can('update', $this->vendor))->toBeFalse()
        // Coverage is the shop's own configuration, never an admin's to edit.
        ->and($this->vendorUser->can('manageDelivery', $this->vendor))->toBeTrue()
        ->and($this->admin->can('manageDelivery', $this->vendor))->toBeFalse()
        ->and($this->admin->can('viewAny', Vendor::class))->toBeTrue()
        ->and($this->vendorUser->can('viewAny', Vendor::class))->toBeFalse()
        ->and($this->admin->can('moderate', Vendor::class))->toBeTrue()
        ->and($this->vendorUser->can('moderate', Vendor::class))->toBeFalse();
});

/**
 * Because the platform takes no commission, every discount is funded entirely by the
 * vendor offering it — so a vendor owns their own coupons and only an admin may touch
 * a platform-wide one.
 */
it('gives a vendor their own coupons and an admin the platform ones', function (): void {
    $mine = Coupon::factory()->create(['vendor_id' => $this->vendor->id]);
    $theirs = Coupon::factory()->create(['vendor_id' => $this->rival->id]);
    $platformWide = Coupon::factory()->create(['vendor_id' => null]);

    expect($this->vendorUser->can('viewAny', Coupon::class))->toBeTrue()
        ->and($this->admin->can('viewAny', Coupon::class))->toBeTrue()
        ->and($this->customer->can('viewAny', Coupon::class))->toBeFalse();

    expect($this->vendorUser->can('create', Coupon::class))->toBeTrue()
        ->and($this->admin->can('create', Coupon::class))->toBeTrue()
        ->and($this->customer->can('create', Coupon::class))->toBeFalse();

    foreach (['view', 'update', 'delete'] as $ability) {
        expect($this->vendorUser->can($ability, $mine))->toBeTrue()
            ->and($this->vendorUser->can($ability, $theirs))->toBeFalse()
            ->and($this->vendorUser->can($ability, $platformWide))->toBeFalse()
            ->and($this->admin->can($ability, $platformWide))->toBeTrue()
            ->and($this->customer->can($ability, $mine))->toBeFalse();
    }
});
