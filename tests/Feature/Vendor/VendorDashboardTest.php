<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Models\VendorOrder;

/**
 * The vendor's own shop at a glance, deferred so the frame and the subscription banner
 * render before the aggregates land.
 *
 * "Cash collected" is money the vendor already took at the door — never a balance and
 * never an amount owed, because the platform never touched it.
 */
beforeEach(function (): void {
    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);
});

it('renders the dashboard shell with the shop name and the fee', function (): void {
    $this->actingAs($this->vendorUser)
        ->get(route('vendor.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/dashboard')
            ->where('shopName', $this->vendor->shop_name)
            ->has('subscriptionFee')
            ->missing('stats'),
        );
});

it('counts what the vendor still has to act on and what they collected', function (): void {
    VendorOrder::factory()->for($this->vendor)->create(['status' => OrderStatus::Pending]);
    VendorOrder::factory()->for($this->vendor)->create(['status' => OrderStatus::Confirmed]);
    VendorOrder::factory()->for($this->vendor)->create(['status' => OrderStatus::Shipped]);

    VendorOrder::factory()->for($this->vendor)->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => now(),
        'total' => 45000,
    ]);

    // Delivered last year: real history, but not this month's cash.
    VendorOrder::factory()->for($this->vendor)->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subYear(),
        'total' => 900000,
    ]);

    Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 50, 'low_stock_threshold' => 5]);
    Product::factory()->for($this->vendor)->published()->lowStock()->create();
    Product::factory()->for($this->vendor)->create(['stock_quantity' => 0]);
    Product::factory()->for($this->vendor)->archived()->create(['stock_quantity' => 0]);

    VendorDeliveryArea::factory()->for($this->vendor)->districtWide()->create();
    VendorDeliveryArea::factory()->for($this->vendor)->inactive()->create();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.dashboard'), inertiaPartial('vendor/dashboard', ['stats']))
        ->assertOk()
        ->assertJsonPath('props.stats.orders_needing_action', 2)
        ->assertJsonPath('props.stats.orders_in_delivery', 1)
        ->assertJsonPath('props.stats.low_stock_count', 2)
        ->assertJsonPath('props.stats.out_of_stock_count', 1)
        ->assertJsonPath('props.stats.published_count', 2)
        ->assertJsonPath('props.stats.draft_count', 1)
        ->assertJsonPath('props.stats.cash_collected_this_month', 45000)
        ->assertJsonPath('props.stats.delivered_this_month', 1)
        ->assertJsonPath('props.stats.active_delivery_areas', 1)
        ->assertJsonPath('props.stats.currency', 'RWF');
});

it('lists the orders still waiting on the vendor', function (): void {
    $pending = VendorOrder::factory()->for($this->vendor)->create(['status' => OrderStatus::Pending]);
    OrderItem::factory()->count(2)->for($pending)->create(['order_id' => $pending->order_id]);

    VendorOrder::factory()->for($this->vendor)->create(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);
    VendorOrder::factory()->for($this->vendor)->create(['status' => OrderStatus::Cancelled]);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.dashboard'), inertiaPartial('vendor/dashboard', ['actionableOrders']))
        ->assertOk()
        ->assertJsonCount(1, 'props.actionableOrders')
        ->assertJsonPath('props.actionableOrders.0.order_number', $pending->order_number)
        ->assertJsonPath('props.actionableOrders.0.item_count', 2);
});

it('lists what is running out, worst first, and ignores archived stock', function (): void {
    $empty = Product::factory()->for($this->vendor)->published()->create([
        'stock_quantity' => 0,
        'low_stock_threshold' => 5,
    ]);

    $low = Product::factory()->for($this->vendor)->published()->create([
        'stock_quantity' => 3,
        'low_stock_threshold' => 5,
    ]);

    Product::factory()->for($this->vendor)->published()->create([
        'stock_quantity' => 80,
        'low_stock_threshold' => 5,
    ]);

    Product::factory()->for($this->vendor)->archived()->create([
        'stock_quantity' => 0,
        'low_stock_threshold' => 5,
    ]);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.dashboard'), inertiaPartial('vendor/dashboard', ['lowStockProducts']))
        ->assertOk()
        ->assertJsonCount(2, 'props.lowStockProducts')
        ->assertJsonPath('props.lowStockProducts.0.name', $empty->name)
        ->assertJsonPath('props.lowStockProducts.0.status', ProductStatus::Published->value)
        ->assertJsonPath('props.lowStockProducts.1.name', $low->name);
});

/**
 * Locking an expired vendor out of their own dashboard would only make renewing
 * harder — they still have orders to deliver and a catalog to look after.
 */
it('stays open to a vendor whose subscription has lapsed', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($expired->user)
        ->get(route('vendor.dashboard'))
        ->assertOk();
});
