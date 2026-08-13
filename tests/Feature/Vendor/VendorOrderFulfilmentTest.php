<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;

/**
 * The vendor's own slice of a customer order.
 *
 * The customer's phone number and address are shown rather than hidden: the vendor is
 * the one who has to find the house and collect the cash, and there is no courier
 * layer to relay them through. What must never leak is anything belonging to another
 * vendor in the same basket.
 */
beforeEach(function (): void {
    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);

    $this->customer = User::factory()->customer()->create();
});

/**
 * A vendor order with one line, on an order that has a real delivery address.
 */
function fulfilmentOrder(User $customer, Vendor $vendor, OrderStatus $status = OrderStatus::Pending): VendorOrder
{
    $address = Address::factory()->for($customer)->create();

    $order = Order::factory()->for($customer)->create(['shipping_address_id' => $address->id]);

    $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create(['status' => $status]);

    OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create(['stock_quantity' => 10]))
        ->create(['quantity' => 2]);

    return $vendorOrder;
}

it('lists the vendor own orders with what each may become next', function (): void {
    $vendorOrder = fulfilmentOrder($this->customer, $this->vendor);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $vendorOrder->order_number)
            ->where('orders.data.0.item_count', 1)
            ->where('orders.data.0.customer_name', $this->customer->name)
            ->has('orders.data.0.allowed_transitions', 2)
            ->where('orders.data.0.allowed_transitions.0.value', OrderStatus::Confirmed->value)
            ->where('orders.data.0.allowed_transitions.0.label', 'Confirmed'),
        );
});

it('searches vendor orders by order number and filters by status', function (): void {
    $target = fulfilmentOrder($this->customer, $this->vendor, OrderStatus::Shipped);
    fulfilmentOrder($this->customer, $this->vendor);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.orders.index', ['search' => $target->order_number]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $target->uuid)
            ->where('filters.search', $target->order_number),
        );

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.orders.index', ['status' => OrderStatus::Shipped->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $target->uuid)
            ->where('filters.status', OrderStatus::Shipped->value),
        );
});

it('ignores a status filter that is not a real status', function (): void {
    fulfilmentOrder($this->customer, $this->vendor);
    fulfilmentOrder($this->customer, $this->vendor);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.orders.index', ['status' => 'imaginary']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data', 2));
});

it('shows the vendor everything they need to deliver and nothing more', function (): void {
    $vendorOrder = fulfilmentOrder($this->customer, $this->vendor);
    $order = $vendorOrder->order;
    $item = $vendorOrder->items->first();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.orders.show', $vendorOrder))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/orders/show')
            ->where('vendorOrder.order_number', $vendorOrder->order_number)
            ->where('vendorOrder.total', $vendorOrder->total)
            ->where('vendorOrder.currency', $order->currency)
            ->where('vendorOrder.customer_order_number', $order->order_number)
            ->where('vendorOrder.customer.name', $this->customer->name)
            ->where('vendorOrder.customer.phone', $order->shippingAddress->phone)
            ->where('vendorOrder.delivery_address.landmark', $order->shippingAddress->landmark)
            ->has('vendorOrder.items', 1)
            ->where('vendorOrder.items.0.sku', $item->sku),
        );
});

it('advances an order one legal step at a time', function (): void {
    $vendorOrder = fulfilmentOrder($this->customer, $this->vendor);

    foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
        $this->actingAs($this->vendorUser)
            ->from(route('vendor.orders.show', $vendorOrder))
            ->put(route('vendor.orders.status.update', $vendorOrder), ['status' => $status->value])
            ->assertRedirect(route('vendor.orders.show', $vendorOrder));

        expect($vendorOrder->fresh()->status)->toBe($status);
    }

    expect($vendorOrder->fresh()->delivered_at)->not->toBeNull()
        // The parent order follows its vendor orders rather than carrying its own state.
        ->and($vendorOrder->order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('refuses an illegal jump through the lifecycle', function (): void {
    $vendorOrder = fulfilmentOrder($this->customer, $this->vendor);

    $this->actingAs($this->vendorUser)
        ->from(route('vendor.orders.index'))
        ->put(route('vendor.orders.status.update', $vendorOrder), ['status' => OrderStatus::Delivered->value])
        ->assertRedirect(route('vendor.orders.index'));

    expect($vendorOrder->fresh()->status)->toBe(OrderStatus::Pending);
});

it('refuses a status that is not a real status', function (): void {
    $vendorOrder = fulfilmentOrder($this->customer, $this->vendor);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.orders.status.update', $vendorOrder), ['status' => 'teleported'])
        ->assertSessionHasErrors('status');
});

it('returns committed stock to the catalog when a vendor cancels', function (): void {
    $vendorOrder = fulfilmentOrder($this->customer, $this->vendor);
    $product = $vendorOrder->items->first()->product;

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.orders.status.update', $vendorOrder), ['status' => OrderStatus::Cancelled->value])
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(12)
        ->and($vendorOrder->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($vendorOrder->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('returns variant stock rather than product stock when the line had a variant', function (): void {
    $order = Order::factory()->for($this->customer)->create();
    $vendorOrder = VendorOrder::factory()->for($order)->for($this->vendor)->create();

    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 10]);
    $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 4]);

    OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for($product)
        ->create(['product_variant_id' => $variant->id, 'quantity' => 3]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.orders.status.update', $vendorOrder), ['status' => OrderStatus::Cancelled->value])
        ->assertRedirect();

    expect($variant->fresh()->stock_quantity)->toBe(7)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

it('survives cancelling a line whose product was deleted from the catalog', function (): void {
    $vendorOrder = fulfilmentOrder($this->customer, $this->vendor);
    $vendorOrder->items->first()->product->delete();

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.orders.status.update', $vendorOrder), ['status' => OrderStatus::Cancelled->value])
        ->assertRedirect();

    expect($vendorOrder->fresh()->status)->toBe(OrderStatus::Cancelled);
});

/**
 * On a multi-vendor basket the customer is still waiting for the least advanced shop,
 * so that is the status the parent order shows.
 */
it('holds the parent order at the least advanced shop', function (): void {
    $order = Order::factory()->for($this->customer)->create();

    $mine = VendorOrder::factory()->for($order)->for($this->vendor)->create(['status' => OrderStatus::Pending]);
    VendorOrder::factory()->for($order)->create(['status' => OrderStatus::Shipped]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.orders.status.update', $mine), ['status' => OrderStatus::Confirmed->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

it('ignores a cancelled shop when deriving the parent order status', function (): void {
    $order = Order::factory()->for($this->customer)->create();

    $mine = VendorOrder::factory()->for($order)->for($this->vendor)->create(['status' => OrderStatus::Pending]);
    VendorOrder::factory()->for($order)->create(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.orders.status.update', $mine), ['status' => OrderStatus::Cancelled->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('still lets a lapsed vendor deliver orders they already accepted', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $vendorOrder = fulfilmentOrder($this->customer, $expired, OrderStatus::Shipped);

    $this->actingAs($expired->user)
        ->put(route('vendor.orders.status.update', $vendorOrder), ['status' => OrderStatus::Delivered->value])
        ->assertRedirect();

    expect($vendorOrder->fresh()->status)->toBe(OrderStatus::Delivered);
});
