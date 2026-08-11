<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Models\District;
use App\Models\Sector;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Declares where a vendor delivers.
 *
 * Passing no sectors covers the whole district in one row — the common case, and the
 * reason the screen is two clicks rather than thirty. Passing sectors creates one row
 * each, and those rows override the district-wide row for their sector at checkout.
 *
 * Both database constraints are enforced here rather than left to the schema:
 *
 * - A sector must belong to the district on the same row. Nothing in the schema ties
 *   the two columns together, and a mismatched pair does not error — it silently
 *   never matches an address, so the vendor appears to deliver nowhere.
 * - A vendor may not declare the same area twice. The unique index catches repeated
 *   sector rows, but MySQL does not treat NULLs as equal, so a second district-wide
 *   row would slip straight past it.
 */
final readonly class AddDeliveryCoverage
{
    /**
     * @param  array<int, string>  $sectorUuids  Empty covers the whole district.
     * @return Collection<int, VendorDeliveryArea>
     *
     * @throws ValidationException
     */
    public function handle(
        Vendor $vendor,
        District $district,
        array $sectorUuids,
        int $deliveryFee,
        int $estimatedDaysMin,
        int $estimatedDaysMax,
        bool $isActive = true,
    ): Collection {
        if ($estimatedDaysMax < $estimatedDaysMin) {
            throw ValidationException::withMessages([
                'estimated_days_max' => __('The longest estimate cannot be shorter than the shortest one.'),
            ]);
        }

        $sectors = $this->resolveSectors($district, $sectorUuids);

        return DB::transaction(function () use ($vendor, $district, $sectors, $deliveryFee, $estimatedDaysMin, $estimatedDaysMax, $isActive): Collection {
            $this->guardAgainstDuplicates($vendor, $district, $sectors);

            $targets = $sectors->isEmpty()
                ? collect([null])
                : $sectors->map(fn (Sector $sector): int => $sector->id);

            return $targets->map(fn (?int $sectorId): VendorDeliveryArea => VendorDeliveryArea::query()->create([
                'vendor_id' => $vendor->id,
                'district_id' => $district->id,
                'sector_id' => $sectorId,
                'delivery_fee' => $deliveryFee,
                'estimated_days_min' => $estimatedDaysMin,
                'estimated_days_max' => $estimatedDaysMax,
                'is_active' => $isActive,
            ]))->values();
        });
    }

    /**
     * @param  array<int, string>  $sectorUuids
     * @return Collection<int, Sector>
     *
     * @throws ValidationException
     */
    private function resolveSectors(District $district, array $sectorUuids): Collection
    {
        $sectorUuids = array_values(array_unique($sectorUuids));

        if ($sectorUuids === []) {
            /** @var Collection<int, Sector> */
            return collect();
        }

        $sectors = Sector::query()
            ->where('district_id', $district->id)
            ->whereIn('uuid', $sectorUuids)
            ->get();

        if ($sectors->count() !== count($sectorUuids)) {
            throw ValidationException::withMessages([
                'sector_ids' => __('Every sector must belong to :district.', ['district' => $district->name]),
            ]);
        }

        return $sectors;
    }

    /**
     * @param  Collection<int, Sector>  $sectors
     *
     * @throws ValidationException
     */
    private function guardAgainstDuplicates(Vendor $vendor, District $district, Collection $sectors): void
    {
        $existing = VendorDeliveryArea::query()
            ->where('vendor_id', $vendor->id)
            ->where('district_id', $district->id)
            ->lockForUpdate()
            ->get();

        if ($sectors->isEmpty()) {
            if ($existing->contains(fn (VendorDeliveryArea $area): bool => $area->sector_id === null)) {
                throw ValidationException::withMessages([
                    'district_id' => __('You already deliver across the whole of :district.', ['district' => $district->name]),
                ]);
            }

            return;
        }

        $taken = $existing->pluck('sector_id')->filter()->all();
        $clash = $sectors->firstWhere(fn (Sector $sector): bool => in_array($sector->id, $taken, true));

        if ($clash instanceof Sector) {
            throw ValidationException::withMessages([
                'sector_ids' => __('You already deliver to :sector.', ['sector' => $clash->name]),
            ]);
        }
    }
}
