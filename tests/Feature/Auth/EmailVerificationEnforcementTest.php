<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

/**
 * Every authenticated area of this app is gated behind the `verified` middleware, but
 * that gate is a no-op unless User implements MustVerifyEmail: both the middleware and
 * Fortify's registration listener decide what to do by testing `instanceof` against the
 * contract, not by reading `email_verified_at`.
 *
 * The trait alone — which Authenticatable already supplies — makes `hasVerifiedEmail()`
 * answer correctly while the gate silently admits everyone, so these tests assert the
 * enforcement rather than the column.
 */
beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('the user model implements the contract that arms verification', function (): void {
    expect(User::factory()->make())->toBeInstanceOf(MustVerifyEmail::class);
});

test('unverified users are turned away from verified routes', function (): void {
    $this->actingAs(User::factory()->unverified()->customer()->create());

    $this->get(route('account.orders.index'))
        ->assertRedirect(route('verification.notice'));
});

test('verified users reach verified routes', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    $this->get(route('account.orders.index'))->assertOk();
});

test('registering sends a verification notification', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    (new SendEmailVerificationNotification)->handle(new Registered($user));

    Notification::assertSentTo($user, VerifyEmail::class);
});
