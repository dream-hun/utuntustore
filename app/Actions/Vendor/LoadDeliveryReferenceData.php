<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Models\District;
use App\Models\Province;
use App\Models\Sector;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Serves Rwanda's administrative divisions to the delivery coverage screen.
 *
 * The country has 5 provinces, 30 districts and 416 sectors. Shipping all 416
 * sectors on every page load would be the single biggest payload in the vendor
 * area for users who are mostly on phones on slow connections, so the page gets
 * only the 30 districts and pulls one district's sectors on demand.
 *
 * The data is seeded reference data that only changes when MINALOC revises the
 * territorial divisions, so it is cached for a day rather than queried per visit.
 */
final readonly class LoadDeliveryReferenceData
{
    private const int CACHE_TTL = 86_400;

    public function __construct(private CacheRepository $cache) {}

    /**
     * Every district, grouped by province, for the district picker.
     *
     * @return array<int, array{id: string, name: string, districts: array<int, array{id: string, name: string}>}>
     */
    public function districts(): array
    {
        /** @var array<int, array{id: string, name: string, districts: array<int, array{id: string, name: string}>}> */
        return $this->cache->remember(
            'vendor.delivery.districts',
            self::CACHE_TTL,
            fn (): array => Province::query()
                ->with(['districts' => fn (Relation $query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Province $province): array => [
                    'id' => $province->uuid,
                    'name' => $province->name,
                    'districts' => $province->districts
                        ->map(fn (District $district): array => [
                            'id' => $district->uuid,
                            'name' => $district->name,
                        ])
                        ->all(),
                ])
                ->all(),
        );
    }

    /**
     * The sectors of a single district, loaded only when a vendor opens that district.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function sectors(District $district): array
    {
        /** @var array<int, array{id: string, name: string}> */
        return $this->cache->remember(
            "vendor.delivery.sectors.{$district->id}",
            self::CACHE_TTL,
            fn (): array => $district->sectors()
                ->orderBy('name')
                ->get()
                ->map(fn (Sector $sector): array => [
                    'id' => $sector->uuid,
                    'name' => $sector->name,
                ])
                ->all(),
        );
    }
}
