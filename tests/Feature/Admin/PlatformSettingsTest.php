<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Support\Settings;

/**
 * The subscription terms are read when a payment is recorded and copied onto that row,
 * so changing them here is forward-looking only: no historical subscription is ever
 * rewritten, and the ledger keeps saying what each vendor actually paid.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('shows the current terms next to how many shops are live on them', function (): void {
    VendorSubscription::factory()->active()->count(2)->create();
    VendorSubscription::factory()->cancelled()->create();

    $settings = resolve(Settings::class);

    $this->actingAs($this->admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings')
            ->where('settings.vendor_subscription_fee', $settings->subscriptionFee())
            ->where('settings.vendor_subscription_currency', $settings->subscriptionCurrency())
            ->where('settings.vendor_subscription_days', $settings->subscriptionDays())
            ->where('settings.vendor_subscription_grace_days', $settings->subscriptionGraceDays())
            ->where('liveSubscriptions', 2),
        );
});

it('saves new terms and uppercases the currency', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), [
            'vendor_subscription_fee' => 25000,
            'vendor_subscription_currency' => 'usd',
            'vendor_subscription_days' => 180,
            'vendor_subscription_grace_days' => 14,
        ])
        ->assertRedirect();

    $settings = resolve(Settings::class);
    $settings->flush();

    expect($settings->subscriptionFee())->toBe(25000)
        ->and($settings->subscriptionCurrency())->toBe('USD')
        ->and($settings->subscriptionDays())->toBe(180)
        ->and($settings->subscriptionGraceDays())->toBe(14);
});

it('never rewrites what an existing subscription was charged', function (): void {
    $vendor = Vendor::factory()->approved()->create();
    $subscription = VendorSubscription::factory()->for($vendor)->active()->create(['amount' => 20000]);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), [
            'vendor_subscription_fee' => 99000,
            'vendor_subscription_currency' => 'RWF',
            'vendor_subscription_days' => 365,
            'vendor_subscription_grace_days' => 7,
        ])
        ->assertRedirect();

    expect($subscription->fresh()->amount)->toBe(20000);
});

it('refuses a fee that is not a whole number of francs', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), [
            'vendor_subscription_fee' => '20000.50',
            'vendor_subscription_currency' => 'RWF',
            'vendor_subscription_days' => 365,
            'vendor_subscription_grace_days' => 7,
        ])
        ->assertSessionHasErrors('vendor_subscription_fee');
});

it('refuses terms outside their allowed range', function (array $payload, string $field): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), [
            'vendor_subscription_fee' => 20000,
            'vendor_subscription_currency' => 'RWF',
            'vendor_subscription_days' => 365,
            'vendor_subscription_grace_days' => 7,
            ...$payload,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'negative fee' => [['vendor_subscription_fee' => -1], 'vendor_subscription_fee'],
    'four letter currency' => [['vendor_subscription_currency' => 'RWFX'], 'vendor_subscription_currency'],
    'zero day period' => [['vendor_subscription_days' => 0], 'vendor_subscription_days'],
    'year long grace' => [['vendor_subscription_grace_days' => 400], 'vendor_subscription_grace_days'],
]);
