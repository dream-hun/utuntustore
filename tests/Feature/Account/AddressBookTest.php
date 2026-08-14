<?php

declare(strict_types=1);

use App\Enums\AddressType;
use App\Models\Address;
use App\Models\District;
use App\Models\Order;
use App\Models\Sector;
use App\Models\User;

/**
 * The customer's address book, end to end.
 *
 * The pair (district, sector) is what vendor delivery coverage is matched on, so the
 * rules that keep the pair consistent — and the rule that keeps exactly one default
 * alive — are the ones worth pinning down here.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create();
    $this->sector = Sector::factory()->create();
    $this->district = $this->sector->district;
});

/**
 * A valid address payload pointing at the shared district/sector pair.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addressPayload(Sector $sector, array $overrides = []): array
{
    return [
        'type' => AddressType::Shipping->value,
        'first_name' => 'Aline',
        'last_name' => 'Uwase',
        'phone' => '+250788000111',
        'district' => $sector->district->uuid,
        'sector' => $sector->uuid,
        'cell' => 'Kacyiru',
        'village' => 'Kamatamu',
        'address_line' => 'KG 7 Ave',
        'landmark' => 'Near the church',
        'is_default' => false,
        ...$overrides,
    ];
}

it('lists the address book with the district picker', function (): void {
    Address::factory()->for($this->customer)->create(['sector_id' => $this->sector->id]);

    $this->actingAs($this->customer)
        ->get(route('account.addresses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/addresses/index')
            ->has('addresses.data', 1)
            ->has('districts', 1)
            ->where('selectedDistrict', null)
            ->where('addresses.data.0.district.province.name', $this->district->province->name),
        );
});

it('serves one district worth of sectors when a district is picked', function (): void {
    Sector::factory()->count(2)->create(['district_id' => $this->district->id]);
    Sector::factory()->create();

    $this->actingAs($this->customer)
        ->get(route('account.addresses.index', ['district' => $this->district->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedDistrict', $this->district->uuid)
            ->has('sectors', 3),
        );
});

it('sends no sectors before a district has been picked', function (): void {
    $this->actingAs($this->customer)
        ->get(route('account.addresses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sectors', 0));
});

it('saves a new address and derives its district from the sector', function (): void {
    $this->actingAs($this->customer)
        ->post(route('account.addresses.store'), addressPayload($this->sector))
        ->assertRedirect(route('account.addresses.index'));

    $address = Address::query()->sole();

    expect($address->user_id)->toBe($this->customer->id)
        ->and($address->district_id)->toBe($this->district->id)
        ->and($address->sector_id)->toBe($this->sector->id)
        ->and($address->country)->toBe('RW')
        ->and($address->cell)->toBe('Kacyiru')
        ->and($address->landmark)->toBe('Near the church');
});

it('makes the very first saved address the default even when not asked to', function (): void {
    $this->actingAs($this->customer)
        ->post(route('account.addresses.store'), addressPayload($this->sector, ['is_default' => false]))
        ->assertRedirect();

    expect(Address::query()->sole()->is_default)->toBeTrue();
});

it('stores blank optional parts as null rather than empty strings', function (): void {
    $this->actingAs($this->customer)
        ->post(route('account.addresses.store'), addressPayload($this->sector, [
            'cell' => '',
            'village' => '',
            'address_line' => '',
            'landmark' => '',
        ]))
        ->assertRedirect();

    $address = Address::query()->sole();

    expect($address->cell)->toBeNull()
        ->and($address->village)->toBeNull()
        ->and($address->address_line)->toBeNull()
        ->and($address->landmark)->toBeNull();
});

it('promotes a later address to default when the customer asks', function (): void {
    $first = Address::factory()->for($this->customer)->isDefault()->create();

    $this->actingAs($this->customer)
        ->post(route('account.addresses.store'), addressPayload($this->sector, ['is_default' => true]))
        ->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($this->customer->addresses()->where('is_default', true)->count())->toBe(1);
});

it('updates an address the customer owns', function (): void {
    $address = Address::factory()->for($this->customer)->create(['first_name' => 'Old']);

    $this->actingAs($this->customer)
        ->put(route('account.addresses.update', $address), addressPayload($this->sector, [
            'first_name' => 'New',
        ]))
        ->assertRedirect(route('account.addresses.index'));

    expect($address->fresh()->first_name)->toBe('New');
});

it('never lowers a default while editing it', function (): void {
    $address = Address::factory()->for($this->customer)->isDefault()->create();

    $this->actingAs($this->customer)
        ->put(route('account.addresses.update', $address), addressPayload($this->sector, [
            'is_default' => false,
        ]))
        ->assertRedirect();

    expect($address->fresh()->is_default)->toBeTrue();
});

it('rejects a sector that is not inside the chosen district', function (): void {
    $foreignDistrict = District::factory()->create();

    $this->actingAs($this->customer)
        ->post(route('account.addresses.store'), addressPayload($this->sector, [
            'district' => $foreignDistrict->uuid,
        ]))
        ->assertSessionHasErrors('sector');

    expect(Address::query()->count())->toBe(0);
});

it('does not run the district check once the payload already failed validation', function (): void {
    $this->actingAs($this->customer)
        ->post(route('account.addresses.store'), addressPayload($this->sector, [
            'first_name' => '',
            'sector' => '',
        ]))
        ->assertSessionHasErrors(['first_name', 'sector'])
        ->assertSessionDoesntHaveErrors('district');
});

it('deletes an address the customer owns', function (): void {
    $address = Address::factory()->for($this->customer)->create();

    $this->actingAs($this->customer)
        ->delete(route('account.addresses.destroy', $address))
        ->assertRedirect(route('account.addresses.index'));

    expect(Address::query()->whereKey($address->id)->exists())->toBeFalse();
});

it('promotes the newest survivor when the default address is deleted', function (): void {
    $survivor = Address::factory()->for($this->customer)->create(['is_default' => false]);
    $default = Address::factory()->for($this->customer)->isDefault()->create();

    $this->actingAs($this->customer)
        ->delete(route('account.addresses.destroy', $default))
        ->assertRedirect();

    expect($survivor->fresh()->is_default)->toBeTrue();
});

it('leaves the default alone when a non-default address is deleted', function (): void {
    $default = Address::factory()->for($this->customer)->isDefault()->create();
    $other = Address::factory()->for($this->customer)->create(['is_default' => false]);

    $this->actingAs($this->customer)
        ->delete(route('account.addresses.destroy', $other))
        ->assertRedirect();

    expect($default->fresh()->is_default)->toBeTrue();
});

it('refuses to delete an address an order was delivered to', function (): void {
    $address = Address::factory()->for($this->customer)->create();

    Order::factory()->for($this->customer)->create([
        'shipping_address_id' => $address->id,
        'billing_address_id' => $address->id,
    ]);

    $this->actingAs($this->customer)
        ->delete(route('account.addresses.destroy', $address))
        ->assertRedirect(route('account.addresses.index'));

    expect(Address::query()->whereKey($address->id)->exists())->toBeTrue();
});

it('leaves nothing default when the last address is deleted', function (): void {
    $only = Address::factory()->for($this->customer)->isDefault()->create();

    $this->actingAs($this->customer)
        ->delete(route('account.addresses.destroy', $only))
        ->assertRedirect();

    expect(Address::query()->where('user_id', $this->customer->id)->count())->toBe(0);
});
