<?php

declare(strict_types=1);

use App\Actions\Vendor\BuildVendorSalesReport;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Models\VendorOrder;

/**
 * The marketplace isolation boundary.
 *
 * Two vendors share every route in the vendor area, so the only thing keeping one
 * shop out of another's products, orders and customers is the policy layer. These
 * tests exist to make a regression there loud rather than silent.
 */
beforeEach(function (): void {
    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);

    $this->rival = Vendor::factory()->sellable()->create();
    $this->rivalUser = $this->rival->user;
    $this->rivalUser->update(['role' => UserRole::Vendor]);
});

/**
 * Build a delivered vendor order for a vendor, with one item and a real customer.
 */
function vendorOrderFor(Vendor $vendor): VendorOrder
{
    $customer = User::factory()->customer()->create();
    $order = Order::factory()->for($customer)->create();

    $vendorOrder = VendorOrder::factory()
        ->for($order)
        ->for($vendor)
        ->create();

    OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create())
        ->create();

    return $vendorOrder;
}

it('does not list another vendor products', function (): void {
    $mine = Product::factory()->for($this->vendor)->create(['name' => 'My own product']);
    $theirs = Product::factory()->for($this->rival)->create(['name' => 'Rival product']);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $mine->uuid),
        );

    expect($theirs->vendor_id)->not->toBe($this->vendor->id);
});

it('forbids updating another vendor product', function (): void {
    $theirs = Product::factory()->for($this->rival)->create();

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.update', $theirs), [
            'name' => 'Hijacked',
            'category_id' => $theirs->category->uuid,
            'price' => 1000,
            'stock_quantity' => 1,
            'low_stock_threshold' => 1,
        ])
        ->assertForbidden();

    expect($theirs->fresh()->name)->not->toBe('Hijacked');
});

it('forbids deleting another vendor product', function (): void {
    $theirs = Product::factory()->for($this->rival)->create();

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.products.destroy', $theirs))
        ->assertForbidden();

    expect(Product::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('forbids publishing another vendor product', function (): void {
    $theirs = Product::factory()->for($this->rival)->create();

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.publish', $theirs))
        ->assertForbidden();

    expect($theirs->fresh()->published_at)->toBeNull();
});

it('forbids adjusting stock on another vendor product', function (): void {
    $theirs = Product::factory()->for($this->rival)->create(['stock_quantity' => 5]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.inventory.products.update', $theirs), ['stock_quantity' => 999])
        ->assertForbidden();

    expect($theirs->fresh()->stock_quantity)->toBe(5);
});

it('forbids adjusting stock on another vendor variant', function (): void {
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for($this->rival)->create())
        ->create(['stock_quantity' => 5]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.inventory.variants.update', $variant), ['stock_quantity' => 999])
        ->assertForbidden();

    expect($variant->fresh()->stock_quantity)->toBe(5);
});

it('does not list another vendor orders', function (): void {
    $mine = vendorOrderFor($this->vendor);
    vendorOrderFor($this->rival);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $mine->uuid),
        );
});

/**
 * The one that matters most: a vendor order carries the customer's phone number and
 * home address, so reaching another vendor's order leaks a stranger's personal data.
 */
it('forbids viewing another vendor order and its customer address', function (): void {
    $theirs = vendorOrderFor($this->rival);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.orders.show', $theirs))
        ->assertForbidden();
});

it('forbids advancing another vendor order', function (): void {
    $theirs = vendorOrderFor($this->rival);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.orders.status.update', $theirs), [
            'status' => OrderStatus::Confirmed->value,
        ])
        ->assertForbidden();

    expect($theirs->fresh()->status)->toBe(OrderStatus::Pending);
});

it('does not list another vendor delivery areas', function (): void {
    $mine = VendorDeliveryArea::factory()->for($this->vendor)->districtWide()->create();
    VendorDeliveryArea::factory()->for($this->rival)->districtWide()->create();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.delivery.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('areas', 1)
            ->where('areas.0.id', $mine->uuid),
        );
});

it('hides another vendor delivery area behind a 404', function (): void {
    $theirs = VendorDeliveryArea::factory()->for($this->rival)->districtWide()->create([
        'delivery_fee' => 1000,
    ]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.delivery.update', $theirs), [
            'delivery_fee' => 1,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertNotFound();

    expect($theirs->fresh()->delivery_fee)->toBe(1000);
});

it('forbids removing another vendor delivery area', function (): void {
    $theirs = VendorDeliveryArea::factory()->for($this->rival)->districtWide()->create();

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.delivery.destroy', $theirs))
        ->assertNotFound();

    expect(VendorDeliveryArea::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('shows a vendor only their own subscription history', function (): void {
    $this->actingAs($this->vendorUser)
        ->get(route('vendor.subscription.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'subscription.status',
            $this->vendor->subscription_status->value,
        ));
});

it('reports sales from the signed-in vendor only', function (): void {
    $mine = vendorOrderFor($this->vendor);
    $mine->update(['status' => OrderStatus::Delivered, 'delivered_at' => now(), 'total' => 50_000]);

    $theirs = vendorOrderFor($this->rival);
    $theirs->update(['status' => OrderStatus::Delivered, 'delivered_at' => now(), 'total' => 900_000]);

    // The report is a deferred prop, so asserting it over HTTP would mean reproducing
    // Inertia's partial-request handshake. The scoping rule lives in the Action, so
    // that is what gets asserted; the page itself is covered by the smoke test above.
    $report = resolve(BuildVendorSalesReport::class)->handle(
        $this->vendor,
        now()->subYear(),
        now()->addDay(),
    );

    expect($report['collected'])->toBe(50_000)
        ->and($report['order_count'])->toBe(1);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.sales.index', ['period' => 'all']))
        ->assertOk();
});

it('refuses the whole vendor area to a customer', function (): void {
    $customer = User::factory()->customer()->create();

    foreach ([
        route('vendor.dashboard'),
        route('vendor.products.index'),
        route('vendor.orders.index'),
        route('vendor.delivery.index'),
        route('vendor.sales.index'),
        route('vendor.subscription.index'),
    ] as $url) {
        $this->actingAs($customer)->get($url)->assertForbidden();
    }
});

it('refuses the vendor area to an admin who owns no shop', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('vendor.dashboard'))->assertForbidden();
});
