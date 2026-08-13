<?php

declare(strict_types=1);

use App\Actions\Vendor\AddDeliveryCoverage;
use App\Actions\Vendor\AdjustStock;
use App\Actions\Vendor\SetProductPublication;
use App\Actions\Vendor\UpdateDeliveryArea;
use App\Enums\UserRole;
use App\Models\District;
use App\Models\Product;
use App\Models\Sector;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use Illuminate\Validation\ValidationException;

/**
 * Where a vendor delivers decides whether their shop can be ordered from at all.
 *
 * The screen is built around the answer almost every vendor gives first — "everywhere
 * in my district" — which is one row with a null sector, not thirty. Individual
 * sectors are added on top and override that row.
 *
 * None of this is gated on selling eligibility: coverage is shop configuration, and an
 * expired vendor must be able to have it ready for the day they renew.
 */
beforeEach(function (): void {
    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);

    $this->sector = Sector::factory()->create();
    $this->district = $this->sector->district;
});

it('lists coverage with the district-wide row heading its own group', function (): void {
    $wide = VendorDeliveryArea::factory()->for($this->vendor)->create([
        'district_id' => $this->district->id,
        'sector_id' => null,
    ]);

    $sectorArea = VendorDeliveryArea::factory()->for($this->vendor)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.delivery.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/delivery')
            ->has('provinces', 1)
            ->has('areas', 2)
            ->where('areas.0.id', $wide->uuid)
            ->where('areas.0.sector', null)
            ->where('areas.0.district.name', $this->district->name)
            ->where('areas.1.id', $sectorArea->uuid)
            ->where('areas.1.sector.name', $this->sector->name)
            ->where('sectorsDistrictId', null),
        );
});

it('never ships a sector list until the browser asks for one', function (): void {
    Sector::factory()->count(2)->create(['district_id' => $this->district->id]);

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.delivery.index', ['district' => $this->district->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sectorsDistrictId', $this->district->uuid)
            ->missing('sectors'),
        );

    $this->actingAs($this->vendorUser)
        ->get(
            route('vendor.delivery.index', ['district' => $this->district->uuid]),
            inertiaPartial('vendor/delivery', ['sectors']),
        )
        ->assertOk()
        ->assertJsonCount(3, 'props.sectors');
});

it('serves no sectors when the requested district does not exist', function (): void {
    $this->actingAs($this->vendorUser)
        ->get(
            route('vendor.delivery.index', ['district' => 'no-such-district']),
            inertiaPartial('vendor/delivery', ['sectors']),
        )
        ->assertOk()
        ->assertJsonCount(0, 'props.sectors')
        ->assertJsonPath('props.sectorsDistrictId', null);
});

it('covers a whole district in one row', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'delivery_fee' => 1500,
            'estimated_days_min' => 1,
            'estimated_days_max' => 3,
        ])
        ->assertRedirect(route('vendor.delivery.index'));

    $area = VendorDeliveryArea::query()->sole();

    expect($area->sector_id)->toBeNull()
        ->and($area->district_id)->toBe($this->district->id)
        ->and($area->delivery_fee)->toBe(1500)
        ->and($area->is_active)->toBeTrue();
});

it('creates one row per chosen sector', function (): void {
    $second = Sector::factory()->create(['district_id' => $this->district->id]);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'sector_ids' => [$this->sector->uuid, $second->uuid],
            'delivery_fee' => 800,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertRedirect();

    expect(VendorDeliveryArea::query()->count())->toBe(2)
        ->and(VendorDeliveryArea::query()->whereNull('sector_id')->count())->toBe(0);
});

it('ignores a sector listed twice in the same submission', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'sector_ids' => [$this->sector->uuid, $this->sector->uuid],
            'delivery_fee' => 500,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertRedirect();

    expect(VendorDeliveryArea::query()->count())->toBe(1);
});

/**
 * A sector on the wrong district does not error at the database level — it silently
 * never matches an address, so the vendor appears to deliver nowhere.
 */
it('refuses a sector that belongs to a different district', function (): void {
    $foreign = Sector::factory()->create();

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'sector_ids' => [$foreign->uuid],
            'delivery_fee' => 500,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertSessionHasErrors('sector_ids');

    expect(VendorDeliveryArea::query()->count())->toBe(0);
});

/**
 * MySQL does not treat NULLs as equal, so a second district-wide row would slip
 * straight past the unique index.
 */
it('refuses a second district-wide row for the same district', function (): void {
    VendorDeliveryArea::factory()->for($this->vendor)->create([
        'district_id' => $this->district->id,
        'sector_id' => null,
    ]);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'delivery_fee' => 500,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertSessionHasErrors('district_id');

    expect(VendorDeliveryArea::query()->count())->toBe(1);
});

it('refuses a sector the vendor already covers', function (): void {
    VendorDeliveryArea::factory()->for($this->vendor)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'sector_ids' => [$this->sector->uuid],
            'delivery_fee' => 500,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertSessionHasErrors('sector_ids');

    expect(VendorDeliveryArea::query()->count())->toBe(1);
});

it('allows a sector row on top of an existing district-wide row', function (): void {
    VendorDeliveryArea::factory()->for($this->vendor)->create([
        'district_id' => $this->district->id,
        'sector_id' => null,
    ]);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'sector_ids' => [$this->sector->uuid],
            'delivery_fee' => 200,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(VendorDeliveryArea::query()->count())->toBe(2);
});

it('refuses a longest estimate shorter than the shortest one', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'delivery_fee' => 500,
            'estimated_days_min' => 5,
            'estimated_days_max' => 2,
        ])
        ->assertSessionHasErrors('estimated_days_max');
});

it('reprices an area the vendor already covers', function (): void {
    $area = VendorDeliveryArea::factory()->for($this->vendor)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
        'delivery_fee' => 2000,
    ]);

    $this->actingAs($this->vendorUser)
        ->put(route('vendor.delivery.update', $area), [
            'delivery_fee' => 0,
            'estimated_days_min' => 1,
            'estimated_days_max' => 4,
            'is_active' => false,
        ])
        ->assertRedirect(route('vendor.delivery.index'));

    $area->refresh();

    expect($area->delivery_fee)->toBe(0)
        ->and($area->estimated_days_max)->toBe(4)
        ->and($area->is_active)->toBeFalse()
        // The district and sector are immutable: an area is added or removed, never moved.
        ->and($area->sector_id)->toBe($this->sector->id);
});

it('removes an area the vendor no longer covers', function (): void {
    $area = VendorDeliveryArea::factory()->for($this->vendor)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.delivery.destroy', $area))
        ->assertRedirect(route('vendor.delivery.index'));

    expect(VendorDeliveryArea::query()->count())->toBe(0);
});

it('lets a lapsed vendor have their coverage ready for the day they renew', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($expired->user)
        ->post(route('vendor.delivery.store'), [
            'district_id' => $this->district->uuid,
            'delivery_fee' => 1000,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ])
        ->assertRedirect();

    expect(VendorDeliveryArea::query()->where('vendor_id', $expired->id)->count())->toBe(1);
});

/**
 * The form rejects these before they reach the Actions, but the rules live in the
 * Actions so they hold for jobs and commands too — which is where these assertions
 * have to drive them from.
 */
it('rejects an impossible estimate range when coverage is added directly', function (): void {
    resolve(AddDeliveryCoverage::class)->handle($this->vendor, $this->district, [], 500, 5, 2);
})->throws(ValidationException::class);

it('rejects an impossible estimate range when an area is edited directly', function (): void {
    $area = VendorDeliveryArea::factory()->for($this->vendor)->districtWide()->create();

    resolve(UpdateDeliveryArea::class)->handle($area, 500, 5, 2, true);
})->throws(ValidationException::class);

it('rejects negative stock when it is set directly', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    resolve(AdjustStock::class)->handle($product, -1);
})->throws(ValidationException::class);

/**
 * The route carries `vendor.can-sell` and the policy refuses first, so this guard is
 * only reachable from a job or a command — which is exactly why it is repeated inside
 * the Action rather than left to the middleware.
 */
it('refuses to publish for a lapsed vendor even with no middleware in the way', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $product = Product::factory()->for($expired)->create();

    resolve(SetProductPublication::class)->handle($product, true);
})->throws(ValidationException::class);
