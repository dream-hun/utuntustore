<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;

/**
 * Platform health at a glance. Every figure is deferred, so each is asserted through
 * the partial reload Inertia sends for it.
 *
 * The one that matters: revenue is subscriptions and nothing else. Order totals are
 * gross merchandise value the platform never takes a share of.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('renders the dashboard shell with every figure still deferred', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->missing('metrics')
            ->missing('revenue'),
        );
});

it('counts vendors by application status and by selling eligibility', function (): void {
    Vendor::factory()->create(['status' => VendorStatus::Pending]);
    $selling = Vendor::factory()->sellable()->create();
    Vendor::factory()->grace()->create();
    Vendor::factory()->expired()->create();
    Vendor::factory()->rejected()->create();
    Vendor::factory()->suspended()->create();

    User::factory()->customer()->count(3)->create();
    VendorSubscription::factory()->for($selling)->active()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'), inertiaPartial('admin/dashboard', ['metrics']))
        ->assertOk()
        ->assertJsonPath('props.metrics.vendors.total', 6)
        ->assertJsonPath('props.metrics.vendors.pending', 1)
        ->assertJsonPath('props.metrics.vendors.approved', 3)
        ->assertJsonPath('props.metrics.vendors.rejected', 1)
        ->assertJsonPath('props.metrics.vendors.suspended', 1)
        ->assertJsonPath('props.metrics.selling.active', 1)
        ->assertJsonPath('props.metrics.selling.grace', 1)
        ->assertJsonPath('props.metrics.selling.expired', 1)
        ->assertJsonPath('props.metrics.selling.none', 3)
        ->assertJsonPath('props.metrics.active_subscriptions', 1)
        // The admin, the vendor owners and the three customers: only customers count.
        ->assertJsonPath('props.metrics.customers', 3);
});

it('counts revenue from paid subscriptions only', function (): void {
    VendorSubscription::factory()->active()->create(['amount' => 20000, 'paid_at' => now()]);
    VendorSubscription::factory()->active()->create(['amount' => 20000, 'paid_at' => now()->subMonths(6)]);

    // Recorded but never confirmed paid, and a cancelled period: neither is revenue.
    VendorSubscription::factory()->create(['amount' => 999999]);
    VendorSubscription::factory()->cancelled()->create(['amount' => 999999, 'paid_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'), inertiaPartial('admin/dashboard', ['revenue']))
        ->assertOk()
        ->assertJsonPath('props.revenue.all_time.total', 40000)
        ->assertJsonPath('props.revenue.all_time.count', 2)
        ->assertJsonPath('props.revenue.all_time.currency', 'RWF')
        ->assertJsonPath('props.revenue.this_year.total', 40000);
});

it('lists the shops whose selling rights are about to lapse', function (): void {
    $soon = Vendor::factory()->sellable()->create(['subscription_ends_at' => now()->addDays(5)]);
    $lapsing = Vendor::factory()->grace()->create(['subscription_ends_at' => now()->subDay()]);

    // Outside the window, and a platform shop that is exempt from the subscription.
    Vendor::factory()->sellable()->create(['subscription_ends_at' => now()->addDays(90)]);
    Vendor::factory()->platformOwned()->create(['subscription_ends_at' => now()->addDay()]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'), inertiaPartial('admin/dashboard', ['upcomingExpiries']))
        ->assertOk()
        ->assertJsonCount(2, 'props.upcomingExpiries')
        ->assertJsonPath('props.upcomingExpiries.0.shop_name', $lapsing->shop_name)
        ->assertJsonPath('props.upcomingExpiries.0.subscription_status', SubscriptionStatus::Grace->value)
        ->assertJsonPath('props.upcomingExpiries.1.shop_name', $soon->shop_name);
});

it('lists the newest arrivals on both sides of the marketplace', function (): void {
    $vendor = Vendor::factory()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'), inertiaPartial('admin/dashboard', ['recentSignups']))
        ->assertOk()
        ->assertJsonPath('props.recentSignups.vendors.0.shop_name', $vendor->shop_name)
        ->assertJsonPath('props.recentSignups.vendors.0.status', $vendor->status->value)
        ->assertJsonPath('props.recentSignups.customers.0.email', $customer->email);
});
