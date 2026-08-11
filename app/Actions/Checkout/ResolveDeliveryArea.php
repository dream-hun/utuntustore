<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Models\Address;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves which of a vendor's delivery areas applies to a given address.
 *
 * Coverage is matched most-specific-first: a row naming the exact sector wins over
 * the vendor's district-wide row, which is what lets a vendor say "everywhere in
 * Gasabo for 2,000, but Remera for 1,000".
 *
 * No match means the vendor does not deliver there, and their items cannot be
 * ordered to that address at all. This is a hard rule at checkout — at browse time
 * it is only a filter.
 */
final readonly class ResolveDeliveryArea
{
    public function handle(Vendor $vendor, Address $address): ?VendorDeliveryArea
    {
        return $this->query($address)
            ->where('vendor_id', $vendor->id)
            ->first();
    }

    /**
     * Resolve the applicable area for many vendors in one query.
     *
     * Checkout needs this for every vendor in the cart at once; doing it per vendor
     * would put a query per shop on the critical path of every checkout page load.
     *
     * @param  array<int, int>  $vendorIds
     * @return array<int, VendorDeliveryArea> Keyed by vendor id.
     */
    public function forVendors(array $vendorIds, Address $address): array
    {
        if ($vendorIds === []) {
            return [];
        }

        $areas = $this->query($address)
            ->whereIn('vendor_id', $vendorIds)
            ->get();

        $resolved = [];

        foreach ($areas as $area) {
            // The query orders exact-sector matches first, so the first row seen for
            // a vendor is the most specific one that applies.
            $resolved[$area->vendor_id] ??= $area;
        }

        return $resolved;
    }

    /**
     * @return Builder<VendorDeliveryArea>
     */
    private function query(Address $address): Builder
    {
        return VendorDeliveryArea::query()
            ->where('is_active', true)
            ->where('district_id', $address->district_id)
            ->where(function (Builder $query) use ($address): void {
                $query->where('sector_id', $address->sector_id)
                    ->orWhereNull('sector_id');
            })
            // Exact sector match before the district-wide fallback.
            ->orderByRaw('sector_id IS NULL')
            ->orderBy('id');
    }
}
