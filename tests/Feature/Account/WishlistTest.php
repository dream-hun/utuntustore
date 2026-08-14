<?php

declare(strict_types=1);

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wishlist;
use App\Models\WishlistItem;

/**
 * A saved product is not a reservation, so the list shows live availability and moving
 * one into the basket goes back through AddToCart. When that refuses, the item has to
 * stay saved — losing it because a shop went out of stock would be the worse failure.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create();
    $this->vendor = Vendor::factory()->sellable()->create();
});

it('lists saved products with their live availability', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 4]);

    $wishlist = Wishlist::factory()->for($this->customer)->create();
    WishlistItem::factory()->for($wishlist)->for($product)->create();

    $this->actingAs($this->customer)
        ->get(route('account.wishlist.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/wishlist/index')
            ->has('items.data', 1)
            ->where('items.data.0.product.name', $product->name)
            ->where('items.data.0.product.in_stock', true)
            ->where('items.data.0.product.primary_image_url', null)
            ->where('items.data.0.product.vendor.can_sell', true),
        );
});

it('shows a saved product from a lapsed shop as unable to sell', function (): void {
    $expiredVendor = Vendor::factory()->expired()->create();
    $product = Product::factory()->for($expiredVendor)->published()->outOfStock()->create();

    $wishlist = Wishlist::factory()->for($this->customer)->create();
    WishlistItem::factory()->for($wishlist)->for($product)->create();

    $this->actingAs($this->customer)
        ->get(route('account.wishlist.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.product.in_stock', false)
            ->where('items.data.0.product.vendor.can_sell', false),
        );
});

it('creates the wishlist on first save', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();

    $this->actingAs($this->customer)
        ->from(route('products.show', $product))
        ->post(route('account.wishlist.store'), ['product' => $product->uuid])
        ->assertRedirect(route('products.show', $product));

    expect(Wishlist::query()->where('user_id', $this->customer->id)->count())->toBe(1)
        ->and(WishlistItem::query()->count())->toBe(1);
});

it('treats saving the same product twice as a no-op', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($this->customer)
            ->post(route('account.wishlist.store'), ['product' => $product->uuid])
            ->assertRedirect();
    }

    expect(WishlistItem::query()->count())->toBe(1);
});

it('refuses to save a product that does not exist', function (): void {
    $this->actingAs($this->customer)
        ->post(route('account.wishlist.store'), ['product' => 'not-a-product'])
        ->assertSessionHasErrors('product');
});

it('removes a saved product', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();

    $wishlist = Wishlist::factory()->for($this->customer)->create();
    WishlistItem::factory()->for($wishlist)->for($product)->create();

    $this->actingAs($this->customer)
        ->delete(route('account.wishlist.destroy', $product))
        ->assertRedirect();

    expect(WishlistItem::query()->count())->toBe(0);
});

it('moves a saved product into the cart', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 3]);

    $wishlist = Wishlist::factory()->for($this->customer)->create();
    WishlistItem::factory()->for($wishlist)->for($product)->create();

    $this->actingAs($this->customer)
        ->post(route('account.wishlist.cart', $product))
        ->assertRedirect();

    expect(WishlistItem::query()->count())->toBe(0)
        ->and(CartItem::query()->where('product_id', $product->id)->sum('quantity'))->toBe(1);
});

it('keeps a saved product on the wishlist when the cart refuses it', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->outOfStock()->create();

    $wishlist = Wishlist::factory()->for($this->customer)->create();
    WishlistItem::factory()->for($wishlist)->for($product)->create();

    $this->actingAs($this->customer)
        ->post(route('account.wishlist.cart', $product))
        ->assertRedirect();

    expect(WishlistItem::query()->count())->toBe(1)
        ->and(CartItem::query()->count())->toBe(0);
});

it('keeps a saved product when its shop can no longer sell', function (): void {
    $expiredVendor = Vendor::factory()->expired()->create();
    $product = Product::factory()->for($expiredVendor)->published()->create(['stock_quantity' => 9]);

    $wishlist = Wishlist::factory()->for($this->customer)->create();
    WishlistItem::factory()->for($wishlist)->for($product)->create();

    $this->actingAs($this->customer)
        ->post(route('account.wishlist.cart', $product))
        ->assertRedirect();

    expect(WishlistItem::query()->count())->toBe(1)
        ->and(CartItem::query()->count())->toBe(0);
});
