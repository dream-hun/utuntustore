<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;

/**
 * Ownership and selling eligibility are deliberately separate checks.
 *
 * An expired vendor still owns their shop: they keep the dashboard, their orders,
 * their sales history and their catalog. What they lose is the right to put new
 * inventory in front of customers. Getting this wrong in either direction is bad —
 * locking them out loses a vendor who is about to pay, and letting them publish
 * gives away the only thing the subscription actually buys.
 */
beforeEach(function (): void {
    $this->vendor = Vendor::factory()->expired()->create();
    $this->user = $this->vendor->user;
    $this->user->update(['role' => UserRole::Vendor]);
});

it('confirms the fixture really cannot sell', function (): void {
    expect($this->vendor->canSell())->toBeFalse();
});

it('still lets an expired vendor reach every read-only screen', function (): void {
    foreach ([
        'vendor.dashboard',
        'vendor.products.index',
        'vendor.inventory.index',
        'vendor.orders.index',
        'vendor.delivery.index',
        'vendor.sales.index',
        'vendor.shop.edit',
        'vendor.subscription.index',
    ] as $name) {
        $this->actingAs($this->user)
            ->get(route($name))
            ->assertOk();
    }
});

/**
 * The subscription page is the one screen an expired vendor most needs, because it
 * is where they find out how to pay. Locking them out of it would be self-defeating.
 */
it('keeps the subscription page reachable and tells them selling has stopped', function (): void {
    $this->actingAs($this->user)
        ->get(route('vendor.subscription.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('subscription.can_sell', false));
});

it('lets an expired vendor edit a product they already own', function (): void {
    $product = Product::factory()->for($this->vendor)->create(['name' => 'Old name']);

    $this->actingAs($this->user)
        ->post(route('vendor.products.update', $product), [
            'name' => 'New name',
            'category_id' => $product->category->uuid,
            'price' => 5000,
            'stock_quantity' => 3,
            'low_stock_threshold' => 1,
        ])
        ->assertRedirect();

    expect($product->fresh()->name)->toBe('New name');
});

it('lets an expired vendor adjust their own stock', function (): void {
    $product = Product::factory()->for($this->vendor)->create(['stock_quantity' => 2]);

    $this->actingAs($this->user)
        ->put(route('vendor.inventory.products.update', $product), ['stock_quantity' => 9])
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(9);
});

it('lets an expired vendor unpublish, because that removes inventory rather than adding it', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();

    $this->actingAs($this->user)
        ->delete(route('vendor.products.unpublish', $product))
        ->assertRedirect();

    expect($product->fresh()->status)->not->toBe(ProductStatus::Published);
});

it('refuses to let an expired vendor publish', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    $this->actingAs($this->user)
        ->post(route('vendor.products.publish', $product))
        ->assertForbidden();

    expect($product->fresh()->published_at)->toBeNull();
});

it('refuses to let an expired vendor create a new product', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->user)
        ->post(route('vendor.products.store'), [
            'name' => 'Smuggled in',
            'category_id' => $category->uuid,
            'price' => 1000,
            'stock_quantity' => 1,
            'low_stock_threshold' => 1,
        ])
        ->assertForbidden();

    expect(Product::query()->where('name', 'Smuggled in')->exists())->toBeFalse();
});

it('keeps an expired vendor catalog intact and out of the storefront', function (): void {
    Product::factory()->for($this->vendor)->published()->count(3)->create();

    // Nothing was deleted or unpublished...
    expect($this->vendor->products()->where('status', ProductStatus::Published)->count())->toBe(3)
        // ...it simply cannot be bought.
        ->and(Product::query()->sellable()->count())->toBe(0);
});

it('lets a vendor in the grace period keep selling', function (): void {
    $grace = Vendor::factory()->grace()->create();
    $grace->user->update(['role' => UserRole::Vendor]);

    $product = Product::factory()->for($grace)->create();

    expect($grace->canSell())->toBeTrue();

    $this->actingAs($grace->user)
        ->post(route('vendor.products.publish', $product))
        ->assertRedirect();

    expect($product->fresh()->status)->toBe(ProductStatus::Published);
});
