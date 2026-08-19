<?php

declare(strict_types=1);

use App\Actions\Subscriptions\SweepSubscriptions;
use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\Cart;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Notifications\SubscriptionExpiring;
use App\Support\Settings;
use Illuminate\Support\Facades\Notification;

/**
 * The daily jobs that actually enforce the subscription business model. Without the
 * sweep a vendor who stopped paying would keep selling indefinitely; without the
 * reminders nobody would know to pay in the first place.
 *
 * Both are safe to run repeatedly: the sweep derives state from the clock rather than
 * stepping it forward, and the reminder matches one calendar day at a time.
 */
it('prunes abandoned guest carts and leaves signed-in baskets alone', function (): void {
    $days = config('marketplace.cart.guest_lifetime_days');

    $stale = Cart::factory()->create(['user_id' => null, 'session_id' => 'stale-session']);
    $stale->forceFill(['updated_at' => now()->subDays($days + 1)])->saveQuietly();

    $fresh = Cart::factory()->create(['user_id' => null, 'session_id' => 'fresh-session']);

    $customer = User::factory()->customer()->create();
    $owned = Cart::factory()->for($customer)->create();
    $owned->forceFill(['updated_at' => now()->subYears(3)])->saveQuietly();

    $this->artisan('carts:prune')
        ->expectsOutputToContain('Pruned 1 guest cart(s)')
        ->assertSuccessful();

    expect(Cart::query()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(Cart::query()->whereKey($fresh->id)->exists())->toBeTrue()
        ->and(Cart::query()->whereKey($owned->id)->exists())->toBeTrue();
});

it('reports what the sweep moved where', function (): void {
    Vendor::factory()->sellable()->create(['subscription_ends_at' => now()->addMonth()]);
    Vendor::factory()->approved()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subDay(),
    ]);
    Vendor::factory()->approved()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subYear(),
    ]);

    $this->artisan('subscriptions:sweep')
        ->expectsTable(
            ['State', 'Vendors'],
            [['Active', 1], ['Grace', 1], ['Expired', 1]],
        )
        ->assertSuccessful();
});

it('closes the live subscription row when the sweep expires a vendor', function (): void {
    $vendor = Vendor::factory()->approved()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subYear(),
    ]);

    $subscription = VendorSubscription::factory()->for($vendor)->active()->create();

    $this->artisan('subscriptions:sweep')->assertSuccessful();

    expect($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->fresh()->status)->toBe(VendorSubscriptionStatus::Expired);
});

/**
 * The `whereNotNull` in the sweep already excludes these rows, so the guard only fires
 * when a row changes under a long-running chunked sweep — simulated here by clearing
 * the date as each vendor is hydrated.
 */
it('skips a vendor whose end date disappeared mid-sweep', function (): void {
    $vendor = Vendor::factory()->approved()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->subYear(),
    ]);

    Vendor::retrieved(function (Vendor $retrieved): void {
        $retrieved->subscription_ends_at = null;
    });

    $counts = resolve(SweepSubscriptions::class)->handle();

    expect($counts)->toBe(['active' => 0, 'grace' => 0, 'expired' => 0])
        ->and($vendor->fresh()->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('leaves a platform-owned shop out of the sweep entirely', function (): void {
    $platform = Vendor::factory()->platformOwned()->create([
        'subscription_ends_at' => now()->subYear(),
    ]);

    $this->artisan('subscriptions:sweep')->assertSuccessful();

    expect($platform->fresh()->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($platform->fresh()->canSell())->toBeTrue();
});

it('notifies each vendor on their lead day and nobody else', function (): void {
    Notification::fake();

    /** @var array<int, int> $leadDays */
    $leadDays = config('marketplace.subscription.reminder_days');

    $vendors = [];

    foreach ($leadDays as $days) {
        $vendors[$days] = Vendor::factory()->sellable()->create([
            'subscription_ends_at' => now()->addDays($days)->setTime(9, 0),
        ]);
    }

    // Well outside every lead window.
    $safe = Vendor::factory()->sellable()->create(['subscription_ends_at' => now()->addYear()]);

    $this->artisan('subscriptions:remind')
        ->expectsOutputToContain('Sent '.count($leadDays).' reminder(s).')
        ->assertSuccessful();

    foreach ($vendors as $vendor) {
        Notification::assertSentTo(
            $vendor->user,
            SubscriptionExpiring::class,
            fn (SubscriptionExpiring $notification, array $channels): bool => $channels === ['mail', 'database'],
        );
    }

    Notification::assertNotSentTo($safe->user, SubscriptionExpiring::class);
});

it('leaves a shop that already lapsed out of the reminders', function (): void {
    Notification::fake();

    $lapsed = Vendor::factory()->grace()->create(['subscription_ends_at' => now()->addDays(7)]);
    $unapproved = Vendor::factory()->create([
        'status' => VendorStatus::Pending,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => now()->addDays(7),
    ]);
    $platform = Vendor::factory()->platformOwned()->create(['subscription_ends_at' => now()->addDays(7)]);

    $this->artisan('subscriptions:remind')->assertSuccessful();

    Notification::assertNothingSent();

    expect(resolve(SweepSubscriptions::class)->expiringIn(7))->toHaveCount(0)
        ->and([$lapsed->id, $unapproved->id, $platform->id])->toHaveCount(3);
});

it('writes a reminder a vendor can act on', function (): void {
    $settings = resolve(Settings::class);

    $vendor = Vendor::factory()->sellable()->create([
        'shop_name' => 'Ikawa House',
        'subscription_ends_at' => now()->addDay(),
    ]);

    $notification = new SubscriptionExpiring($vendor, 1);

    $mail = $notification->toMail($vendor->user);
    $rendered = implode(' ', [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines]);

    expect($mail->subject)->toBe('Your shop subscription expires tomorrow')
        ->and($rendered)->toContain('Ikawa House')
        ->and($rendered)->toContain(number_format($settings->subscriptionFee()))
        // Expiry never deletes anything, and the email has to say so.
        ->and($rendered)->toContain('never deleted')
        ->and($mail->actionUrl)->toBe(route('vendor.subscription.index'));

    $payload = $notification->toArray($vendor->user);

    expect($payload['type'])->toBe('subscription_expiring')
        ->and($payload['vendor_id'])->toBe($vendor->uuid)
        ->and($payload['days_remaining'])->toBe(1)
        ->and($payload['title'])->toBe('Your shop subscription expires tomorrow');
});

it('counts down in days when there is more than one left', function (): void {
    $vendor = Vendor::factory()->sellable()->create(['subscription_ends_at' => now()->addDays(30)]);

    $notification = new SubscriptionExpiring($vendor, 30);

    expect($notification->toMail($vendor->user)->subject)
        ->toBe('Your shop subscription expires in 30 days');
});

it('still names a deadline when the vendor has no end date recorded', function (): void {
    $vendor = Vendor::factory()->approved()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_ends_at' => null,
    ]);

    expect((new SubscriptionExpiring($vendor, 7))->toArray($vendor->user)['message'])
        ->toContain('Your subscription ends on');
});
