<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;

/**
 * A vendor delivers out of their own stock room, so this screen is how the catalog is
 * kept honest — an oversold product is a doorstep argument, not a refund the platform
 * can process. It stays open whatever the subscription says.
 */
beforeEach(function (): void {
    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);
});

it('lists stock for products and their variants, lowest first', function (): void {
    $low = Product::factory()->for($this->vendor)->create(['stock_quantity' => 1, 'low_stock_threshold' => 5]);
    $plenty = Product::factory()->for($this->vendor)->create(['stock_quantity' => 90, 'low_stock_threshold' => 5]);

    $variant = ProductVariant::factory()->for($plenty)->create(['name' => 'Large']);

    Product::factory()->for($this->vendor)->archived()->create();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.inventory.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/inventory')
            ->has('products.data', 2)
            ->where('products.data.0.id', $low->uuid)
            ->where('products.data.0.is_low_stock', true)
            ->where('products.data.1.id', $plenty->uuid)
            ->where('products.data.1.is_low_stock', false)
            ->has('products.data.1.variants', 1)
            ->where('products.data.1.variants.0.name', $variant->name)
            ->where('products.data.1.variants.0.stock_quantity', $variant->stock_quantity),
        );
});

it('searches inventory by product name and sku', function (): void {
    $target = Product::factory()->for($this->vendor)->create(['name' => 'Findable Rice', 'sku' => 'RICE-9']);
    Product::factory()->for($this->vendor)->create(['name' => 'Other Thing', 'sku' => 'OTH-1']);

    foreach (['Findable', 'RICE-9'] as $term) {
        $this->actingAs($this->vendorUser)
            ->get(route('vendor.inventory.index', ['search' => $term]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $target->uuid)
                ->where('filters.search', $term),
            );
    }
});

it('narrows inventory to what is running low', function (): void {
    $low = Product::factory()->for($this->vendor)->lowStock()->create();
    Product::factory()->for($this->vendor)->create(['stock_quantity' => 100, 'low_stock_threshold' => 5]);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.inventory.index', ['low_stock' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $low->uuid)
            ->where('filters.low_stock', true),
        );
});

it('sets the stock a vendor has on hand for a product', function (): void {
    $product = Product::factory()->for($this->vendor)->create(['stock_quantity' => 3]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.inventory.products.update', $product), ['stock_quantity' => 42])
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(42);
});

it('sets the stock on a single variant', function (): void {
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for($this->vendor)->create())
        ->create(['stock_quantity' => 3]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.inventory.variants.update', $variant), ['stock_quantity' => 17])
        ->assertRedirect();

    expect($variant->fresh()->stock_quantity)->toBe(17);
});

it('refuses a negative stock count', function (): void {
    $product = Product::factory()->for($this->vendor)->create(['stock_quantity' => 5]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.inventory.products.update', $product), ['stock_quantity' => -1])
        ->assertSessionHasErrors('stock_quantity');

    expect($product->fresh()->stock_quantity)->toBe(5);
});

it('lets a lapsed vendor keep their stock count honest', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $product = Product::factory()->for($expired)->create(['stock_quantity' => 2]);

    $this->actingAs($expired->user)
        ->put(route('vendor.inventory.products.update', $product), ['stock_quantity' => 20])
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(20);
});
