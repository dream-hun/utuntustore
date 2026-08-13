<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorSubscription;

/**
 * A record of cash the vendor already collected at the door — never a balance, a payout
 * or an amount owed. The customer handed the money straight to the vendor and the
 * platform was never a party to it; the only figure flowing the other way is the
 * vendor's own subscription, shown as the business expense it is.
 */
beforeEach(function (): void {
    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);
});

/**
 * A delivered vendor order for the given amount, with one line on it.
 */
function deliveredSale(Vendor $vendor, int $total, string $productName, int $quantity = 1, ?string $deliveredAt = null): VendorOrder
{
    $order = Order::factory()->create();

    $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => $deliveredAt ?? now()->toDateTimeString(),
        'total' => $total,
    ]);

    OrderItem::factory()
        ->for($order)
        ->for($vendorOrder)
        ->for(Product::factory()->for($vendor)->published()->create())
        ->create([
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price' => $total,
            'subtotal' => $total * $quantity,
        ]);

    return $vendorOrder;
}

it('renders the sales page with the chosen window resolved server side', function (): void {
    $this->actingAs($this->vendorUser)
        ->get(route('vendor.sales.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/sales')
            ->where('period', 'this_month')
            ->has('range.from')
            ->has('range.to')
            ->missing('report'),
        );
});

it('falls back to this month when the period is not one it offers', function (): void {
    $this->actingAs($this->vendorUser)
        ->get(route('vendor.sales.index', ['period' => 'since-the-beginning-of-time']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('period', 'this_month'));
});

it('accepts each reporting window it offers', function (string $period): void {
    $this->actingAs($this->vendorUser)
        ->get(route('vendor.sales.index', ['period' => $period]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('period', $period));
})->with(['this_month', 'last_month', 'last_30_days', 'this_year', 'all_time']);

it('reports the cash collected, what it was spent on, and the subscription cost', function (): void {
    deliveredSale($this->vendor, 30000, 'Coffee', 2);
    deliveredSale($this->vendor, 10000, 'Tea', 1);

    // Cancelled this month: reported separately, never counted as collected.
    VendorOrder::factory()->for($this->vendor)->create([
        'status' => OrderStatus::Cancelled,
        'total' => 5000,
    ]);

    // Another shop's sale must not appear in this report.
    deliveredSale(Vendor::factory()->sellable()->create(), 999000, 'Not Mine');

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.sales.index', ['period' => 'this_month']), inertiaPartial('vendor/sales', ['report']))
        ->assertOk()
        ->assertJsonPath('props.report.collected', 40000)
        ->assertJsonPath('props.report.order_count', 2)
        ->assertJsonPath('props.report.item_count', 3)
        ->assertJsonPath('props.report.average_order', 20000)
        ->assertJsonPath('props.report.cancelled_count', 1)
        ->assertJsonPath('props.report.cancelled_value', 5000)
        ->assertJsonPath('props.report.currency', 'RWF')
        ->assertJsonPath('props.report.top_products.0.name', 'Coffee')
        ->assertJsonPath('props.report.top_products.0.quantity', 2)
        ->assertJsonPath('props.report.by_month.0.order_count', 2)
        ->assertJsonPath('props.report.subscription_fee', 20000);
});

it('reports a quiet month as zero rather than dividing by it', function (): void {
    $this->actingAs($this->vendorUser)
        ->get(route('vendor.sales.index'), inertiaPartial('vendor/sales', ['report']))
        ->assertOk()
        ->assertJsonPath('props.report.collected', 0)
        ->assertJsonPath('props.report.order_count', 0)
        ->assertJsonPath('props.report.average_order', 0)
        ->assertJsonCount(0, 'props.report.by_month')
        ->assertJsonCount(0, 'props.report.top_products');
});

it('shows the vendor when their selling rights run out', function (): void {
    VendorSubscription::factory()->for($this->vendor)->active()->create();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.sales.index'), inertiaPartial('vendor/sales', ['report']))
        ->assertOk()
        ->assertJsonPath(
            'props.report.subscription_ends_at',
            $this->vendor->subscription_ends_at->toIso8601String(),
        );
});

it('shows the vendor their own subscription history and terms', function (): void {
    $subscription = VendorSubscription::factory()->for($this->vendor)->active()->create();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.subscription.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/subscription')
            ->where('subscription.status', $this->vendor->subscription_status->value)
            ->where('subscription.can_sell', true)
            ->where('subscription.is_platform_owned', false)
            ->where('terms.fee', 20000)
            ->where('terms.currency', 'RWF')
            ->missing('payments'),
        );

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.subscription.index'), inertiaPartial('vendor/subscription', ['payments']))
        ->assertOk()
        ->assertJsonCount(1, 'props.payments')
        ->assertJsonPath('props.payments.0.id', $subscription->uuid)
        ->assertJsonPath('props.payments.0.amount', $subscription->amount)
        ->assertJsonPath('props.payments.0.reference', $subscription->reference);
});

/**
 * It is the one screen an expired vendor most needs, so it carries no can-sell guard.
 */
it('stays reachable when the subscription has expired', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($expired->user)
        ->get(route('vendor.subscription.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('subscription.can_sell', false));
});
