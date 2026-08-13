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

/**
 * Admins see every order and can advance none of them: moving a vendor order to
 * shipped or delivered is a claim about goods physically changing hands, and only the
 * vendor who carried them can make it. The totals here are gross merchandise value —
 * cash handed to the vendor at the door — never platform income.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->customer()->create();
    $this->vendor = Vendor::factory()->sellable()->create();
});

/**
 * A complete order: one vendor order, one line, and a delivery address.
 */
function oversightOrder(User $customer, Vendor $vendor, OrderStatus $status = OrderStatus::Pending): Order
{
    $address = Address::factory()->for($customer)->create();

    $order = Order::factory()->for($customer)->create([
        'status' => $status,
        'shipping_address_id' => $address->id,
        'placed_at' => now(),
    ]);

    $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create(['status' => $status]);

    OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create())
        ->create();

    return $order;
}

it('lists every order on the platform with its shops', function (): void {
    $order = oversightOrder($this->customer, $this->vendor);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number)
            ->where('orders.data.0.customer_name', $this->customer->name)
            ->where('orders.data.0.vendor_orders.0.shop_name', $this->vendor->shop_name),
        );
});

it('filters orders by status', function (): void {
    oversightOrder($this->customer, $this->vendor, OrderStatus::Pending);
    $delivered = oversightOrder($this->customer, $this->vendor, OrderStatus::Delivered);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['status' => OrderStatus::Delivered->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $delivered->uuid)
            ->where('filters.status', OrderStatus::Delivered->value),
        );
});

it('ignores a status filter that is not a real status', function (): void {
    oversightOrder($this->customer, $this->vendor);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['status' => 'imaginary']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

it('searches orders by order number', function (): void {
    $order = oversightOrder($this->customer, $this->vendor);
    oversightOrder($this->customer, $this->vendor);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['search' => $order->order_number]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->uuid)
            ->where('filters.search', $order->order_number),
        );
});

it('narrows orders to a date range', function (): void {
    $recent = oversightOrder($this->customer, $this->vendor);

    $old = oversightOrder($this->customer, $this->vendor);
    $old->update(['placed_at' => now()->subYear()]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', [
            'from' => now()->subWeek()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $recent->uuid)
            ->where('filters.from', now()->subWeek()->toDateString())
            ->where('filters.to', now()->addDay()->toDateString()),
        );
});

it('shows one order with the customer, the address and every shop slice', function (): void {
    $order = oversightOrder($this->customer, $this->vendor);
    $item = OrderItem::query()->sole();

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/show')
            ->where('order.order_number', $order->order_number)
            ->where('order.total', $order->total)
            ->where('order.customer.email', $this->customer->email)
            ->where('order.shipping_address.recipient', $order->shippingAddress->first_name.' '.$order->shippingAddress->last_name)
            ->where('order.vendor_orders.0.vendor.shop_name', $this->vendor->shop_name)
            ->where('order.vendor_orders.0.items.0.sku', $item->sku)
            ->where('order.vendor_orders.0.items.0.product_name', $item->product_name),
        );
});

it('gives an admin no route to advance a vendor order', function (): void {
    $order = oversightOrder($this->customer, $this->vendor);
    $vendorOrder = $order->vendorOrders->first();

    $this->actingAs($this->admin)
        ->put(route('vendor.orders.status.update', $vendorOrder), [
            'status' => OrderStatus::Delivered->value,
        ])
        ->assertForbidden();

    expect($vendorOrder->fresh()->status)->toBe(OrderStatus::Pending);
});
