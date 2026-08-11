<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;

/**
 * What the public may see.
 *
 * A lapsed subscription must hide a shop from every customer-facing surface while
 * leaving its data untouched — that is the entire enforcement mechanism behind the
 * platform's revenue model. If a expired vendor's products stay visible, nobody has
 * any reason to renew.
 */
beforeEach(function (): void {
    $this->category = Category::factory()->create();

    $this->sellable = Vendor::factory()->sellable()->create();
    $this->expired = Vendor::factory()->expired()->create();
});

it('shows a sellable vendor product in the catalog', function (): void {
    $product = Product::factory()
        ->for($this->sellable)
        ->for($this->category)
        ->published()
        ->create();

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->uuid),
        );
});

it('hides an expired vendor product from the catalog', function (): void {
    Product::factory()
        ->for($this->expired)
        ->for($this->category)
        ->published()
        ->create();

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 0));
});

it('hides a draft product from the catalog', function (): void {
    Product::factory()
        ->for($this->sellable)
        ->for($this->category)
        ->create(['status' => ProductStatus::Draft]);

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 0));
});

it('hides a product scheduled for a future release', function (): void {
    Product::factory()
        ->for($this->sellable)
        ->for($this->category)
        ->create([
            'status' => ProductStatus::Published,
            'published_at' => now()->addWeek(),
        ]);

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 0));
});

it('404s the detail page of an expired vendor product', function (): void {
    $product = Product::factory()
        ->for($this->expired)
        ->for($this->category)
        ->published()
        ->create();

    $this->get(route('products.show', $product->slug))->assertNotFound();
});

it('404s the detail page of a draft product', function (): void {
    $product = Product::factory()
        ->for($this->sellable)
        ->for($this->category)
        ->create(['status' => ProductStatus::Draft]);

    $this->get(route('products.show', $product->slug))->assertNotFound();
});

it('serves the detail page of a sellable product', function (): void {
    $product = Product::factory()
        ->for($this->sellable)
        ->for($this->category)
        ->published()
        ->create();

    $this->get(route('products.show', $product->slug))->assertOk();
});

it('404s the shop page of an expired vendor', function (): void {
    $this->get(route('vendors.show', $this->expired->slug))->assertNotFound();
});

it('serves the shop page of a sellable vendor', function (): void {
    $this->get(route('vendors.show', $this->sellable->slug))->assertOk();
});

it('hides an expired vendor product from its category page', function (): void {
    Product::factory()
        ->for($this->expired)
        ->for($this->category)
        ->published()
        ->create();

    $this->get(route('categories.show', $this->category->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 0));
});

/**
 * Expiring is reversible by design: the catalog is never touched, so recording a
 * payment brings the same products straight back.
 */
it('brings the catalog back the moment the vendor becomes sellable again', function (): void {
    $product = Product::factory()
        ->for($this->expired)
        ->for($this->category)
        ->published()
        ->create();

    $this->get(route('shop'))
        ->assertInertia(fn ($page) => $page->has('products.data', 0));

    $this->expired->update([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->addYear(),
    ]);

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->uuid),
        );
});

it('lets a guest browse the storefront without an account', function (): void {
    foreach ([route('home'), route('shop'), route('cart.index')] as $url) {
        $this->get($url)->assertOk();
    }
});

it('sends a guest to login when they try to check out', function (): void {
    $this->get(route('checkout.index'))->assertRedirect(route('login'));
});
