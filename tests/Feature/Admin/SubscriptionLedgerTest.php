<?php

declare(strict_types=1);

use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Support\Settings;

/**
 * Subscriptions are the platform's only income and this ledger is the whole of it.
 * Money moves off-platform; an admin who confirmed receiving it records it here.
 *
 * Rows are never hard-deleted — a period that must stop is cancelled, so the revenue
 * history stays intact.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists the ledger with who recorded each payment', function (): void {
    $vendor = Vendor::factory()->approved()->create();

    $subscription = VendorSubscription::factory()
        ->for($vendor)
        ->active()
        ->create(['recorded_by' => $this->admin->id, 'reference' => 'MOMO-77']);

    $this->actingAs($this->admin)
        ->get(route('admin.subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/subscriptions/index')
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.id', $subscription->uuid)
            ->where('subscriptions.data.0.reference', 'MOMO-77')
            ->where('subscriptions.data.0.recorded_by', $this->admin->name)
            ->where('subscriptions.data.0.vendor.shop_name', $vendor->shop_name)
            ->has('vendors', 1)
            ->where('fee.currency', 'RWF')
            ->where('filters.period', 'all'),
        );
});

it('offers every approved shop for a payment, including lapsed ones', function (): void {
    Vendor::factory()->expired()->create();
    Vendor::factory()->create(['status' => VendorStatus::Pending]);
    Vendor::factory()->rejected()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('vendors', 1)
            ->where('vendors.0.subscription_status', SubscriptionStatus::Expired->value),
        );
});

it('filters the ledger by subscription status', function (): void {
    $cancelled = VendorSubscription::factory()->cancelled()->create(['paid_at' => now()]);
    VendorSubscription::factory()->active()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.subscriptions.index', ['status' => VendorSubscriptionStatus::Cancelled->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.id', $cancelled->uuid)
            ->where('filters.status', VendorSubscriptionStatus::Cancelled->value),
        );
});

it('narrows the ledger to a period', function (string $period, int $expected): void {
    VendorSubscription::factory()->active()->create(['paid_at' => now()]);
    VendorSubscription::factory()->active()->create(['paid_at' => now()->subMonthNoOverflow()->startOfMonth()->addDay()]);
    VendorSubscription::factory()->active()->create(['paid_at' => now()->subYears(3)]);

    $this->actingAs($this->admin)
        ->get(route('admin.subscriptions.index', ['period' => $period]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', $expected)
            ->where('filters.period', $period),
        );
})->with([
    'this month' => ['this_month', 1],
    'last month' => ['last_month', 1],
    'this year' => ['this_year', 2],
    'last 12 months' => ['last_12_months', 2],
]);

it('treats an unknown period as no period filter at all', function (): void {
    VendorSubscription::factory()->active()->create(['paid_at' => now()->subYears(5)]);

    $this->actingAs($this->admin)
        ->get(route('admin.subscriptions.index', ['period' => 'since-forever']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('filters.period', 'all'),
        );
});

it('defers the revenue figures and scopes the filtered one to the chosen status', function (): void {
    VendorSubscription::factory()->active()->create(['amount' => 20000, 'paid_at' => now()]);
    VendorSubscription::factory()->cancelled()->create(['amount' => 5000, 'paid_at' => now()]);

    $this->actingAs($this->admin)
        ->get(
            route('admin.subscriptions.index', ['status' => VendorSubscriptionStatus::Cancelled->value]),
            inertiaPartial('admin/subscriptions/index', ['revenue']),
        )
        ->assertOk()
        ->assertJsonPath('props.revenue.all_time.total', 20000)
        ->assertJsonPath('props.revenue.matching_filter.total', 5000)
        ->assertJsonPath('props.revenue.period.total', 20000);
});

it('defers the renewal pipeline', function (): void {
    $expiring = Vendor::factory()->sellable()->create(['subscription_ends_at' => now()->addDays(10)]);

    $this->actingAs($this->admin)
        ->get(route('admin.subscriptions.index'), inertiaPartial('admin/subscriptions/index', ['upcomingExpiries']))
        ->assertOk()
        ->assertJsonCount(1, 'props.upcomingExpiries')
        ->assertJsonPath('props.upcomingExpiries.0.shop_name', $expiring->shop_name);
});

it('records a payment and copies the fee onto the row', function (): void {
    $vendor = Vendor::factory()->approved()->create();
    $settings = resolve(Settings::class);

    $this->actingAs($this->admin)
        ->post(route('admin.subscriptions.store'), [
            'vendor' => $vendor->uuid,
            'payment_method' => SubscriptionPaymentMethod::MobileMoney->value,
            'reference' => 'MOMO-123',
            'paid_at' => now()->subDay()->toDateString(),
        ])
        ->assertRedirect();

    $subscription = VendorSubscription::query()->sole();

    expect($subscription->amount)->toBe($settings->subscriptionFee())
        ->and($subscription->currency)->toBe($settings->subscriptionCurrency())
        ->and($subscription->status)->toBe(VendorSubscriptionStatus::Active)
        ->and($subscription->recorded_by)->toBe($this->admin->id)
        ->and($vendor->fresh()->canSell())->toBeTrue();
});

it('starts a renewal where the running period ends rather than today', function (): void {
    $vendor = Vendor::factory()->approved()->create();

    $current = VendorSubscription::factory()->for($vendor)->active()->create([
        'ends_at' => now()->addDays(30),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.subscriptions.store'), [
            'vendor' => $vendor->uuid,
            'payment_method' => SubscriptionPaymentMethod::BankTransfer->value,
            'reference' => 'BANK-9',
        ])
        ->assertRedirect();

    $renewal = VendorSubscription::query()->latest('id')->first();

    expect($current->fresh()->status)->toBe(VendorSubscriptionStatus::Expired)
        ->and($renewal->starts_at->toDateString())->toBe($current->ends_at->toDateString());
});

it('refuses a payment with no reference', function (): void {
    $vendor = Vendor::factory()->approved()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.subscriptions.store'), [
            'vendor' => $vendor->uuid,
            'payment_method' => SubscriptionPaymentMethod::Cash->value,
        ])
        ->assertSessionHasErrors('reference');

    expect(VendorSubscription::query()->count())->toBe(0);
});

it('refuses a payment dated in the future', function (): void {
    $vendor = Vendor::factory()->approved()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.subscriptions.store'), [
            'vendor' => $vendor->uuid,
            'payment_method' => SubscriptionPaymentMethod::Cash->value,
            'reference' => 'CASH-1',
            'paid_at' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('paid_at');
});

it('cancels a period without deleting it, and drops the vendor into grace', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $subscription = VendorSubscription::factory()->for($vendor)->active()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.subscriptions.cancel', $subscription))
        ->assertRedirect();

    $vendor->refresh();

    expect($subscription->fresh()->status)->toBe(VendorSubscriptionStatus::Cancelled)
        ->and(VendorSubscription::query()->count())->toBe(1)
        ->and($vendor->subscription_status)->toBe(SubscriptionStatus::Grace);
});

it('expires the vendor straight away when the platform runs no grace period', function (): void {
    resolve(Settings::class)->set('vendor_subscription_grace_days', 0);

    $vendor = Vendor::factory()->sellable()->create();
    $subscription = VendorSubscription::factory()->for($vendor)->active()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.subscriptions.cancel', $subscription))
        ->assertRedirect();

    expect($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::Expired);
});

it('leaves the vendor alone when cancelling a period that was never live', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $endsAt = $vendor->subscription_ends_at;

    $subscription = VendorSubscription::factory()->for($vendor)->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.subscriptions.cancel', $subscription))
        ->assertRedirect();

    $vendor->refresh();

    expect($subscription->fresh()->status)->toBe(VendorSubscriptionStatus::Cancelled)
        ->and($vendor->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($vendor->subscription_ends_at->toDateTimeString())->toBe($endsAt->toDateTimeString());
});
