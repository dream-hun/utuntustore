<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;

/**
 * A customer's addresses, orders and reviews are personal data. Every one of these
 * routes takes a UUID from the URL, so the policy layer is the only thing stopping
 * one customer from walking another's identifiers.
 */
beforeEach(function (): void {
    $this->me = User::factory()->customer()->create();
    $this->stranger = User::factory()->customer()->create();
});

/**
 * A delivered order item belonging to the given customer, ready to be reviewed.
 */
function deliveredItemFor(User $customer): OrderItem
{
    $vendor = Vendor::factory()->sellable()->create();
    $order = Order::factory()->for($customer)->create();

    $vendorOrder = VendorOrder::factory()
        ->for($order)
        ->for($vendor)
        ->create(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);

    return OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create())
        ->create();
}

it('lists only my own addresses', function (): void {
    $mine = Address::factory()->for($this->me)->create();
    Address::factory()->for($this->stranger)->create();

    $this->actingAs($this->me)
        ->get(route('account.addresses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('addresses.data', 1)
            ->where('addresses.data.0.id', $mine->uuid),
        );
});

it('forbids editing a stranger address', function (): void {
    $theirs = Address::factory()->for($this->stranger)->create(['first_name' => 'Original']);

    $this->actingAs($this->me)
        ->put(route('account.addresses.update', $theirs), [
            'type' => 'shipping',
            'first_name' => 'Hijacked',
            'last_name' => 'Person',
            'phone' => '0780000000',
            'district' => $theirs->district->uuid,
            'sector' => $theirs->sector->uuid,
        ])
        ->assertForbidden();

    expect($theirs->fresh()->first_name)->toBe('Original');
});

it('forbids deleting a stranger address', function (): void {
    $theirs = Address::factory()->for($this->stranger)->create();

    $this->actingAs($this->me)
        ->delete(route('account.addresses.destroy', $theirs))
        ->assertForbidden();

    expect(Address::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('forbids making a stranger address my default', function (): void {
    $theirs = Address::factory()->for($this->stranger)->create(['is_default' => false]);

    $this->actingAs($this->me)
        ->post(route('account.addresses.default', $theirs))
        ->assertForbidden();

    expect($theirs->fresh()->is_default)->toBeFalse();
});

it('keeps exactly one default address per customer', function (): void {
    $first = Address::factory()->for($this->me)->create(['is_default' => true]);
    $second = Address::factory()->for($this->me)->create(['is_default' => false]);

    $this->actingAs($this->me)
        ->post(route('account.addresses.default', $second))
        ->assertRedirect();

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse()
        ->and($this->me->addresses()->where('is_default', true)->count())->toBe(1);
});

it('lists only my own orders', function (): void {
    $mine = Order::factory()->for($this->me)->create();
    Order::factory()->for($this->stranger)->create();

    $this->actingAs($this->me)
        ->get(route('account.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $mine->uuid),
        );
});

it('forbids viewing a stranger order', function (): void {
    $theirs = Order::factory()->for($this->stranger)->create();

    $this->actingAs($this->me)
        ->get(route('account.orders.show', $theirs))
        ->assertForbidden();
});

it('forbids cancelling a stranger order', function (): void {
    $theirs = Order::factory()->for($this->stranger)->create(['status' => OrderStatus::Pending]);

    $this->actingAs($this->me)
        ->post(route('account.orders.cancel', $theirs))
        ->assertForbidden();

    expect($theirs->fresh()->status)->toBe(OrderStatus::Pending);
});

/**
 * Once a vendor is on the road with the goods, cancelling is a conversation rather
 * than a button — the platform holds no money it could refund.
 */
it('refuses to cancel an order that has already shipped', function (): void {
    $order = Order::factory()->for($this->me)->create(['status' => OrderStatus::Shipped]);

    $this->actingAs($this->me)
        ->post(route('account.orders.cancel', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('restores stock when a customer cancels', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $product = Product::factory()->for($vendor)->published()->create(['stock_quantity' => 5]);

    $order = Order::factory()->for($this->me)->create(['status' => OrderStatus::Pending]);
    $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create([
        'status' => OrderStatus::Pending,
    ]);

    OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for($product)
        ->create(['quantity' => 2]);

    $this->actingAs($this->me)
        ->post(route('account.orders.cancel', $order))
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(7)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('lists only my own reviews', function (): void {
    $mine = Review::factory()->for($this->me)->create();
    Review::factory()->for($this->stranger)->create();

    $this->actingAs($this->me)
        ->get(route('account.reviews.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('reviews.data', 1)
            ->where('reviews.data.0.id', $mine->uuid),
        );
});

it('forbids editing a stranger review', function (): void {
    $theirs = Review::factory()->for($this->stranger)->create(['rating' => 5]);

    $this->actingAs($this->me)
        ->put(route('account.reviews.update', $theirs), [
            'rating' => 1,
            'title' => 'Sabotage',
            'comment' => 'Not mine to write.',
        ])
        ->assertForbidden();

    expect($theirs->fresh()->rating)->toBe(5);
});

it('forbids deleting a stranger review', function (): void {
    $theirs = Review::factory()->for($this->stranger)->create();

    $this->actingAs($this->me)
        ->delete(route('account.reviews.destroy', $theirs))
        ->assertForbidden();

    expect(Review::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('refuses a review for an item bought by someone else', function (): void {
    $theirItem = deliveredItemFor($this->stranger);

    $this->actingAs($this->me)
        ->post(route('account.reviews.store'), [
            'order_item' => $theirItem->uuid,
            'rating' => 5,
            'title' => 'Never bought this',
            'comment' => 'Not my purchase.',
        ])
        ->assertForbidden();

    expect(Review::query()->count())->toBe(0);
});

it('refuses a review before the vendor marked the order delivered', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $order = Order::factory()->for($this->me)->create();
    $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create([
        'status' => OrderStatus::Shipped,
        'delivered_at' => null,
    ]);

    $item = OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create())
        ->create();

    $this->actingAs($this->me)
        ->post(route('account.reviews.store'), [
            'order_item' => $item->uuid,
            'rating' => 5,
            'title' => 'Too early',
            'comment' => 'Has not arrived.',
        ])
        ->assertForbidden();

    expect(Review::query()->count())->toBe(0);
});

it('refuses a second review for the same purchased item', function (): void {
    $item = deliveredItemFor($this->me);

    $payload = [
        'order_item' => $item->uuid,
        'rating' => 5,
        'title' => 'First',
        'comment' => 'Good.',
    ];

    $this->actingAs($this->me)
        ->post(route('account.reviews.store'), $payload)
        ->assertRedirect();

    expect(Review::query()->count())->toBe(1);

    $this->actingAs($this->me)
        ->post(route('account.reviews.store'), [...$payload, 'title' => 'Second'])
        ->assertForbidden();

    expect(Review::query()->count())->toBe(1);
});

it('starts a new review as pending rather than published', function (): void {
    $item = deliveredItemFor($this->me);

    $this->actingAs($this->me)
        ->post(route('account.reviews.store'), [
            'order_item' => $item->uuid,
            'rating' => 4,
            'title' => 'Solid',
            'comment' => 'Arrived as described.',
        ])
        ->assertRedirect();

    expect(Review::query()->sole()->status)->toBe(ReviewStatus::Pending);
});

it('refuses the whole account area to a guest', function (): void {
    foreach ([
        route('account.index'),
        route('account.addresses.index'),
        route('account.orders.index'),
        route('account.reviews.index'),
        route('account.wishlist.index'),
    ] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});
