<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\Wishlist;
use App\Models\WishlistItem;

/**
 * Every number on the account overview is deferred, so the assertions here go through
 * the partial reload Inertia sends for them — otherwise the closures never run and the
 * page is only ever asserted empty. A partial reload answers with the page object as
 * JSON rather than the root view, which is why these read `props.*` instead of using
 * `assertInertia`.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create();
});

it('renders the account overview shell without resolving its counts', function (): void {
    $this->actingAs($this->customer)
        ->get(route('account.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/index')
            ->missing('recentOrders')
            ->missing('wishlistCount'),
        );
});

it('defers the customer recent orders with the shops on them', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $order = Order::factory()->for($this->customer)->create(['placed_at' => now()]);
    VendorOrder::factory()->for($order)->for($vendor)->create();

    $this->actingAs($this->customer)
        ->get(route('account.index'), inertiaPartial('account/index', ['recentOrders']))
        ->assertOk()
        ->assertJsonCount(1, 'props.recentOrders')
        ->assertJsonPath('props.recentOrders.0.order_number', $order->order_number)
        ->assertJsonPath('props.recentOrders.0.shops.0', $vendor->shop_name);
});

it('defers the default delivery address', function (): void {
    $address = Address::factory()->for($this->customer)->isDefault()->create();

    $this->actingAs($this->customer)
        ->get(route('account.index'), inertiaPartial('account/index', ['defaultAddress']))
        ->assertOk()
        ->assertJsonPath('props.defaultAddress.recipient', $address->first_name.' '.$address->last_name)
        ->assertJsonPath('props.defaultAddress.district', $address->district->name)
        ->assertJsonPath('props.defaultAddress.sector', $address->sector->name);
});

it('reports no default address when the customer has none', function (): void {
    $this->actingAs($this->customer)
        ->get(route('account.index'), inertiaPartial('account/index', ['defaultAddress']))
        ->assertOk()
        ->assertJsonPath('props.defaultAddress', null);
});

it('counts the wishlist once one exists', function (): void {
    $wishlist = Wishlist::factory()->for($this->customer)->create();
    WishlistItem::factory()->count(2)->for($wishlist)->create();

    $this->actingAs($this->customer)
        ->get(route('account.index'), inertiaPartial('account/index', ['wishlistCount']))
        ->assertOk()
        ->assertJsonPath('props.wishlistCount', 2);
});

it('counts an untouched wishlist as zero without creating one', function (): void {
    $this->actingAs($this->customer)
        ->get(route('account.index'), inertiaPartial('account/index', ['wishlistCount']))
        ->assertOk()
        ->assertJsonPath('props.wishlistCount', 0);

    expect(Wishlist::query()->count())->toBe(0);
});

it('defers the delivered purchases still awaiting a review', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $order = Order::factory()->for($this->customer)->create();

    $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $item = OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create())
        ->create();

    $this->actingAs($this->customer)
        ->get(route('account.index'), inertiaPartial('account/index', ['awaitingReview']))
        ->assertOk()
        ->assertJsonPath('props.awaitingReview.count', 1)
        ->assertJsonCount(1, 'props.awaitingReview.items')
        ->assertJsonPath('props.awaitingReview.items.0.product_name', $item->product_name);
});
