<?php

declare(strict_types=1);

use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Actions\Subscriptions\SweepSubscriptions;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;

/**
 * The subscription sweep is the only thing enforcing the platform's revenue model:
 * without it, a vendor who stopped paying would keep selling forever.
 */
it('keeps a vendor active while the period is still running', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->addDays(30),
    ]);

    resolve(SweepSubscriptions::class)->handle();

    expect($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('moves a lapsed vendor into the grace period', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subDay(),
    ]);

    resolve(SweepSubscriptions::class)->handle();

    expect($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::Grace);
});

it('expires a vendor once the grace period is over', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Grace,
        'subscription_ends_at' => now()->subDays(30),
    ]);

    resolve(SweepSubscriptions::class)->handle();

    expect($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::Expired);
});

it('still permits selling during the grace period', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Grace,
        'subscription_ends_at' => now()->subDay(),
    ]);

    expect($vendor->canSell())->toBeTrue();
});

it('is idempotent across repeated runs', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subDays(30),
    ]);

    resolve(SweepSubscriptions::class)->handle();
    $afterFirst = $vendor->fresh()->subscription_status;

    resolve(SweepSubscriptions::class)->handle();
    resolve(SweepSubscriptions::class)->handle();

    expect($vendor->fresh()->subscription_status)->toBe($afterFirst)
        ->toBe(SubscriptionStatus::Expired);
});

it('reconciles a subscription row left active under an already-expired vendor', function (): void {
    // The vendor column and its subscription row can drift apart — set by hand, or a
    // sweep that died between the two writes. A stale active row would keep counting
    // towards platform revenue for a subscription that has actually lapsed.
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Expired,
        'subscription_ends_at' => now()->subDays(60),
    ]);

    $vendor->subscriptions()->create([
        'amount' => 20_000,
        'currency' => 'RWF',
        'status' => VendorSubscriptionStatus::Active,
        'starts_at' => now()->subYear(),
        'ends_at' => now()->subDays(60),
        'payment_method' => SubscriptionPaymentMethod::Cash,
        'paid_at' => now()->subYear(),
    ]);

    resolve(SweepSubscriptions::class)->handle();

    expect($vendor->subscriptions()->where('status', VendorSubscriptionStatus::Active)->count())->toBe(0);
});

it('never expires the platform-owned store', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'is_platform_owned' => true,
        'subscription_status' => SubscriptionStatus::None,
        'subscription_ends_at' => now()->subYear(),
    ]);

    resolve(SweepSubscriptions::class)->handle();

    expect($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::None)
        ->and($vendor->fresh()->canSell())->toBeTrue();
});

it('hides an expired vendor from the storefront without touching their catalog', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subDays(60),
    ]);

    $product = Product::factory()->for($vendor)->published()->create();

    resolve(SweepSubscriptions::class)->handle();

    expect(Product::query()->sellable()->count())->toBe(0)
        // The product itself is untouched — it is still published and still there.
        ->and($product->fresh()->status->value)->toBe('published')
        ->and($vendor->fresh()->products()->count())->toBe(1);
});

it('restores the shop with its catalog intact when the vendor pays again', function (): void {
    $vendor = Vendor::factory()->create([
        'status' => VendorStatus::Approved,
        'subscription_status' => SubscriptionStatus::Expired,
        'subscription_ends_at' => now()->subDays(60),
    ]);

    Product::factory()->for($vendor)->published()->create();

    expect(Product::query()->sellable()->count())->toBe(0);

    resolve(RecordSubscriptionPayment::class)->handle(
        $vendor,
        User::factory()->admin()->create(),
        SubscriptionPaymentMethod::MobileMoney,
        'MOMO-12345',
    );

    expect($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and(Product::query()->sellable()->count())->toBe(1);
});

it('records the fee in force at the time of payment', function (): void {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Approved]);

    $subscription = resolve(RecordSubscriptionPayment::class)->handle(
        $vendor,
        User::factory()->admin()->create(),
        SubscriptionPaymentMethod::Cash,
    );

    expect($subscription->amount)->toBe(20_000)
        ->and($subscription->currency)->toBe('RWF')
        ->and($subscription->status)->toBe(VendorSubscriptionStatus::Active);
});

it('keeps at most one active subscription per vendor', function (): void {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Approved]);
    $admin = User::factory()->admin()->create();

    resolve(RecordSubscriptionPayment::class)->handle($vendor, $admin, SubscriptionPaymentMethod::Cash);
    resolve(RecordSubscriptionPayment::class)->handle($vendor, $admin, SubscriptionPaymentMethod::Cash);

    expect($vendor->subscriptions()->count())->toBe(2)
        ->and($vendor->subscriptions()->where('status', VendorSubscriptionStatus::Active)->count())->toBe(1);
});

it('starts a renewal where the previous period ended so no paid time is lost', function (): void {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Approved]);
    $admin = User::factory()->admin()->create();

    $first = resolve(RecordSubscriptionPayment::class)->handle($vendor, $admin, SubscriptionPaymentMethod::Cash);
    $second = resolve(RecordSubscriptionPayment::class)->handle($vendor, $admin, SubscriptionPaymentMethod::Cash);

    expect($second->starts_at->timestamp)->toBe($first->ends_at->timestamp)
        ->and($second->ends_at->greaterThan($first->ends_at))->toBeTrue();
});
