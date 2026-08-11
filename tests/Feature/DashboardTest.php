<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;

test('guests are redirected to the login page', function (): void {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('customers are sent to their order history', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    $this->get(route('dashboard'))->assertRedirect(route('account.orders.index'));
});

test('admins are sent to the admin area', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
});

test('vendors are sent to the vendor area', function (): void {
    $user = User::factory()->vendor()->create();
    Vendor::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('vendor.dashboard'));
});
