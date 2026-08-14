<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;

/**
 * Suspension is the only lever on this screen, and it is account-level: the role
 * middleware refuses a suspended user everywhere. Nothing is ever deleted — a
 * customer's orders are also the vendors' delivery history.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists customers with what they have spent with vendors', function (): void {
    $customer = User::factory()->customer()->create(['name' => 'Aline Uwase']);

    Order::factory()->for($customer)->create(['total' => 12000]);
    Order::factory()->for($customer)->create(['total' => 8000]);

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/customers/index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Aline Uwase')
            ->where('customers.data.0.orders_count', 2)
            ->where('customers.data.0.orders_total', 20000),
        );
});

it('shows a customer who has never ordered as having spent nothing', function (): void {
    User::factory()->customer()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('customers.data.0.orders_total', 0));
});

it('leaves vendors and admins out of the customer list', function (): void {
    User::factory()->customer()->create();
    Vendor::factory()->sellable()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('customers.data', 1));
});

it('searches customers by name, email and phone', function (): void {
    $target = User::factory()->customer()->create([
        'name' => 'Findable Person',
        'email' => 'findable@example.test',
        'phone' => '+250788123456',
    ]);

    User::factory()->customer()->create(['name' => 'Someone Else']);

    foreach (['Findable', 'findable@example.test', '788123456'] as $term) {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['search' => $term]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.id', $target->uuid)
                ->where('filters.search', $term),
            );
    }
});

it('filters customers by account status', function (): void {
    $suspended = User::factory()->customer()->suspended()->create();
    User::factory()->customer()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index', ['status' => UserStatus::Suspended->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $suspended->uuid)
            ->where('filters.status', UserStatus::Suspended->value),
        );
});

it('ignores a status filter that is not a real status', function (): void {
    User::factory()->customer()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index', ['status' => 'imaginary']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('customers.data', 2));
});

it('suspends a customer', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.customers.status', $customer), [
            'status' => UserStatus::Suspended->value,
        ])
        ->assertRedirect();

    expect($customer->fresh()->status)->toBe(UserStatus::Suspended);
});

it('reinstates a suspended customer', function (): void {
    $customer = User::factory()->customer()->suspended()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.customers.status', $customer), [
            'status' => UserStatus::Active->value,
        ])
        ->assertRedirect();

    expect($customer->fresh()->status)->toBe(UserStatus::Active);
});

it('rejects a status that is not a real status', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.customers.status', $customer), ['status' => 'banished'])
        ->assertSessionHasErrors('status');
});

/**
 * A vendor's account is not suspended from the customer screen — moderating a shop is
 * a different decision with different consequences for their catalog and orders.
 */
it('hides a vendor account behind a 404 on the customer status route', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.customers.status', $vendor->user), [
            'status' => UserStatus::Suspended->value,
        ])
        ->assertNotFound();

    expect($vendor->user->fresh()->status)->toBe(UserStatus::Active);
});
