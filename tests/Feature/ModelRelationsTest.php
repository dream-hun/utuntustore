<?php

declare(strict_types=1);

use App\Enums\VendorSubscriptionStatus;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;

/**
 * Relations and small model helpers that the screens reach only indirectly, plus the
 * redeemability rule — which has to give the same answer in memory and in SQL, since
 * pricing a quote uses one and claiming a redemption uses the other.
 */
it('walks a coupon back to the shop funding it and forward to who used it', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $customer = User::factory()->customer()->create();
    $order = Order::factory()->for($customer)->create();

    $coupon = Coupon::factory()->create(['vendor_id' => $vendor->id]);

    $usage = CouponUsage::factory()->create([
        'coupon_id' => $coupon->id,
        'user_id' => $customer->id,
        'order_id' => $order->id,
        'discount_amount' => 2500,
    ]);

    expect($coupon->vendor->id)->toBe($vendor->id)
        ->and($coupon->usages)->toHaveCount(1)
        ->and($usage->coupon->id)->toBe($coupon->id)
        ->and($usage->user->id)->toBe($customer->id)
        ->and($usage->order->id)->toBe($order->id)
        ->and($usage->discount_amount)->toBe(2500)
        // A redemption is written once and never changed.
        ->and($usage->updated_at)->toBeNull();

    expect($vendor->coupons)->toHaveCount(1);
});

it('agrees with itself about whether a coupon can still be redeemed', function (string $state, bool $redeemable): void {
    $coupon = match ($state) {
        'live' => Coupon::factory()->create(),
        'switched off' => Coupon::factory()->inactive()->create(),
        'not started' => Coupon::factory()->upcoming()->create(),
        'expired' => Coupon::factory()->expired()->create(),
        'used up' => Coupon::factory()->exhausted()->create(),
    };

    expect($coupon->isRedeemable())->toBe($redeemable)
        // The SQL twin has to give the same answer, or a coupon priced into a quote
        // could fail to claim at write time (or worse, the other way round).
        ->and(Coupon::query()->whereKey($coupon->id)->redeemable()->exists())->toBe($redeemable);
})->with([
    'live' => ['live', true],
    'switched off' => ['switched off', false],
    'not started' => ['not started', false],
    'expired' => ['expired', false],
    'used up' => ['used up', false],
]);

it('treats an open-ended coupon as always live', function (): void {
    $coupon = Coupon::factory()->create([
        'starts_at' => null,
        'expires_at' => null,
        'usage_limit' => null,
        'used_count' => 500,
    ]);

    expect($coupon->isRedeemable())->toBeTrue()
        ->and(Coupon::query()->whereKey($coupon->id)->redeemable()->exists())->toBeTrue();
});

it('finds the vendor running subscription and nothing else', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    VendorSubscription::factory()->for($vendor)->expired()->create();
    $current = VendorSubscription::factory()->for($vendor)->active()->create([
        'ends_at' => now()->addYear(),
    ]);
    VendorSubscription::factory()->for($vendor)->active()->create([
        'ends_at' => now()->addMonth(),
    ]);

    expect($vendor->currentSubscription()->id)->toBe($current->id)
        ->and($vendor->currentSubscription()->status)->toBe(VendorSubscriptionStatus::Active);
});

it('reports no running subscription for a shop that never paid', function (): void {
    $vendor = Vendor::factory()->approved()->create();

    VendorSubscription::factory()->for($vendor)->create();

    expect($vendor->currentSubscription())->toBeNull();
});

it('bills an order to the address it is delivered to', function (): void {
    $customer = User::factory()->customer()->create();
    $shipping = Address::factory()->for($customer)->create();
    $billing = Address::factory()->for($customer)->billing()->create();

    $order = Order::factory()->for($customer)->create([
        'shipping_address_id' => $shipping->id,
        'billing_address_id' => $billing->id,
    ]);

    expect($order->shippingAddress->id)->toBe($shipping->id)
        ->and($order->billingAddress->id)->toBe($billing->id);
});
