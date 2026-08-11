<?php

declare(strict_types=1);

use App\Actions\Checkout\ResolveDeliveryArea;
use App\Models\Address;
use App\Models\District;
use App\Models\Sector;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;

/**
 * Delivery coverage decides whether a vendor can serve a customer at all, and at what
 * price. It is the rule most likely to silently break the marketplace, so it is
 * covered here down to the resolution order.
 */
beforeEach(function (): void {
    $this->district = District::factory()->create();
    $this->sector = Sector::factory()->for($this->district)->create();
    $this->otherSector = Sector::factory()->for($this->district)->create();

    $this->address = Address::factory()->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->vendor = Vendor::factory()->sellable()->create();
    $this->resolver = resolve(ResolveDeliveryArea::class);
});

it('resolves a district-wide area when no sector row exists', function (): void {
    $area = VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => null,
        'delivery_fee' => 2000,
    ]);

    expect($this->resolver->handle($this->vendor, $this->address)?->id)->toBe($area->id);
});

it('prefers an exact sector match over the district-wide row', function (): void {
    VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => null,
        'delivery_fee' => 2000,
    ]);

    $sectorArea = VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
        'delivery_fee' => 1000,
    ]);

    $resolved = $this->resolver->handle($this->vendor, $this->address);

    expect($resolved?->id)->toBe($sectorArea->id)
        ->and($resolved?->delivery_fee)->toBe(1000);
});

it('does not apply a sector row belonging to a different sector', function (): void {
    VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => $this->otherSector->id,
        'delivery_fee' => 500,
    ]);

    expect($this->resolver->handle($this->vendor, $this->address))->toBeNull();
});

it('returns nothing when the vendor does not cover the district', function (): void {
    $elsewhere = District::factory()->create();

    VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $elsewhere->id,
        'sector_id' => null,
    ]);

    expect($this->resolver->handle($this->vendor, $this->address))->toBeNull();
});

it('ignores an inactive coverage row', function (): void {
    VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => null,
        'is_active' => false,
    ]);

    expect($this->resolver->handle($this->vendor, $this->address))->toBeNull();
});

it('resolves coverage for many vendors in one query', function (): void {
    $second = Vendor::factory()->sellable()->create();

    VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => null,
        'delivery_fee' => 2000,
    ]);

    VendorDeliveryArea::factory()->create([
        'vendor_id' => $second->id,
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
        'delivery_fee' => 750,
    ]);

    $resolved = $this->resolver->forVendors(
        [$this->vendor->id, $second->id],
        $this->address,
    );

    expect($resolved)->toHaveCount(2)
        ->and($resolved[$this->vendor->id]->delivery_fee)->toBe(2000)
        ->and($resolved[$second->id]->delivery_fee)->toBe(750);
});

it('allows a free delivery area', function (): void {
    VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => null,
        'delivery_fee' => 0,
    ]);

    expect($this->resolver->handle($this->vendor, $this->address)?->delivery_fee)->toBe(0);
});
