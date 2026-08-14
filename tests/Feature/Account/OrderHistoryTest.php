<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;

/**
 * A multi-vendor order is shown as its vendor orders, because that is how the customer
 * experiences it: several shops delivering separately, each paid in cash at the door.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create();
    $this->vendor = Vendor::factory()->sellable()->create();
});

/**
 * An order of the given status with one vendor order and one line on it.
 */
function orderWithOneLine(User $customer, Vendor $vendor, OrderStatus $status = OrderStatus::Pending): Order
{
    $address = Address::factory()->for($customer)->create();

    $order = Order::factory()->for($customer)->create([
        'status' => $status,
        'shipping_address_id' => $address->id,
    ]);

    $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create(['status' => $status]);

    OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create())
        ->create();

    return $order;
}

it('lists a customer orders with the shops that will deliver them', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor);

    $this->actingAs($this->customer)
        ->get(route('account.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number)
            ->where('orders.data.0.can_cancel', true)
            ->where('orders.data.0.vendor_orders.0.vendor.shop_name', $this->vendor->shop_name)
            ->where('filters.status', null)
            ->has('statuses', count(OrderStatus::cases())),
        );
});

it('filters the order list by status', function (): void {
    orderWithOneLine($this->customer, $this->vendor, OrderStatus::Pending);
    $delivered = orderWithOneLine($this->customer, $this->vendor, OrderStatus::Delivered);

    $this->actingAs($this->customer)
        ->get(route('account.orders.index', ['status' => OrderStatus::Delivered->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $delivered->uuid)
            ->where('filters.status', OrderStatus::Delivered->value),
        );
});

it('rejects an unknown status filter', function (): void {
    $this->actingAs($this->customer)
        ->get(route('account.orders.index', ['status' => 'imaginary']))
        ->assertSessionHasErrors('status');
});

it('shows one order with its lines, totals and delivery address', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor);
    $item = $order->vendorOrders->first()->items->first();

    $this->actingAs($this->customer)
        ->get(route('account.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/orders/show')
            ->where('order.order_number', $order->order_number)
            ->where('order.total', $order->total)
            ->where('order.payment_method', $order->payment_method->value)
            ->where('order.shipping_address.recipient', $order->shippingAddress->first_name.' '.$order->shippingAddress->last_name)
            ->where('order.vendor_orders.0.items.0.product_name', $item->product_name)
            ->where('order.vendor_orders.0.items.0.can_review', false)
            ->where('order.vendor_orders.0.vendor.phone', $this->vendor->phone),
        );
});

it('offers a review on a delivered line that has none yet', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor, OrderStatus::Delivered);
    $order->vendorOrders()->update(['delivered_at' => now()]);

    $this->actingAs($this->customer)
        ->get(route('account.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.vendor_orders.0.items.0.can_review', true)
            ->where('order.can_cancel', false),
        );
});

it('keeps showing a line whose product was deleted from the catalog', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor);
    $item = OrderItem::query()->sole();
    $productName = $item->product_name;

    $item->product->delete();

    $this->actingAs($this->customer)
        ->get(route('account.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.vendor_orders.0.items.0.product_id', null)
            ->where('order.vendor_orders.0.items.0.product_name', $productName),
        );
});

it('cancels an order that has not shipped', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor);

    $this->actingAs($this->customer)
        ->from(route('account.orders.show', $order))
        ->post(route('account.orders.cancel', $order))
        ->assertRedirect(route('account.orders.show', $order));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

/**
 * The policy lets an order through while the parent is still cancellable, but each
 * vendor order carries its own lifecycle — when none of them can still be cancelled,
 * the action refuses rather than reporting a cancellation that did not happen.
 */
it('reports a failure when no vendor order can still be cancelled', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor);
    $order->vendorOrders()->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);

    $this->actingAs($this->customer)
        ->from(route('account.orders.index'))
        ->post(route('account.orders.cancel', $order))
        ->assertRedirect(route('account.orders.index'));

    expect($order->fresh()->status)->not->toBe(OrderStatus::Cancelled);
});

it('lists the customer own reviews with the shop they were written about', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor, OrderStatus::Delivered);
    $order->vendorOrders()->update(['delivered_at' => now()]);

    $item = OrderItem::query()->sole();

    $review = Review::factory()
        ->for($this->customer)
        ->for($item->product)
        ->for($item, 'orderItem')
        ->create();

    $this->actingAs($this->customer)
        ->get(route('account.reviews.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/reviews/index')
            ->has('reviews.data', 1)
            ->where('reviews.data.0.id', $review->uuid)
            ->where('reviews.data.0.product_name', $item->product_name)
            ->where('reviews.data.0.shop_name', $this->vendor->shop_name)
            ->has('awaitingReview.data', 0),
        );
});

it('lists the purchases still waiting for a review', function (): void {
    $order = orderWithOneLine($this->customer, $this->vendor, OrderStatus::Delivered);
    $order->vendorOrders()->update(['delivered_at' => now()]);

    $item = OrderItem::query()->sole();

    $this->actingAs($this->customer)
        ->get(route('account.reviews.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('awaitingReview.data', 1)
            ->where('awaitingReview.data.0.product_name', $item->product_name)
            ->where('awaitingReview.data.0.shop_name', $this->vendor->shop_name),
        );
});

it('sends an edited review back for checking', function (): void {
    $review = Review::factory()->for($this->customer)->approved()->create();

    $this->actingAs($this->customer)
        ->put(route('account.reviews.update', $review), [
            'rating' => 2,
            'title' => 'Changed my mind',
            'comment' => 'It stopped working.',
        ])
        ->assertRedirect(route('account.reviews.index'));

    $review->refresh();

    expect($review->rating)->toBe(2)
        ->and($review->title)->toBe('Changed my mind')
        ->and($review->status->value)->toBe('pending');
});

it('clears the optional parts of a review that were left blank', function (): void {
    $review = Review::factory()->for($this->customer)->create();

    $this->actingAs($this->customer)
        ->put(route('account.reviews.update', $review), [
            'rating' => 5,
            'title' => '',
            'comment' => '',
        ])
        ->assertRedirect();

    $review->refresh();

    expect($review->title)->toBeNull()
        ->and($review->comment)->toBeNull();
});

it('refuses a review rating outside one to five', function (): void {
    $review = Review::factory()->for($this->customer)->create();

    $this->actingAs($this->customer)
        ->put(route('account.reviews.update', $review), ['rating' => 9])
        ->assertSessionHasErrors('rating');
});

it('deletes the customer own review', function (): void {
    $review = Review::factory()->for($this->customer)->create();

    $this->actingAs($this->customer)
        ->delete(route('account.reviews.destroy', $review))
        ->assertRedirect(route('account.reviews.index'));

    expect(Review::query()->count())->toBe(0);
});
