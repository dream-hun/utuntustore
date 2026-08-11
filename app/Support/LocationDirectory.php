<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\District;
use App\Models\Sector;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Rwanda's administrative geography: 5 provinces, 30 districts, 416 sectors.
 *
 * This is seeded reference data that changes when the government redraws a boundary,
 * which is to say roughly never — but it was being re-queried on every checkout page
 * load, every address-book page load and every district change, from four different
 * copies of the same query.
 *
 * It is cached whole and served from memory. The sector list is still handed to the
 * browser one district at a time: shipping all 416 to a phone on a slow connection is
 * the difference between the page loading and not, and caching does nothing for that.
 *
 * @phpstan-type DistrictShape array{id: string, name: string, code: string, province: array{id: string, name: string, code: string}}
 * @phpstan-type SectorShape array{id: string, name: string, code: string}
 */
final readonly class LocationDirectory
{
    private const string DISTRICTS_KEY = 'locations.districts';

    private const string SECTORS_KEY = 'locations.sectors';

    /**
     * A day is far longer than this data ever changes in, and short enough that a
     * boundary change does not need a deploy to take effect.
     */
    private const int CACHE_TTL = 86400;

    public function __construct(private CacheRepository $cache) {}

    /**
     * Every district, with the province it belongs to.
     *
     * @return array<int, DistrictShape>
     */
    public function districts(): array
    {
        /** @var array<int, DistrictShape> */
        return $this->cache->remember(
            self::DISTRICTS_KEY,
            self::CACHE_TTL,
            static fn (): array => District::query()
                ->with('province')
                ->orderBy('name')
                ->get()
                ->map(static fn (District $district): array => [
                    'id' => $district->uuid,
                    'name' => $district->name,
                    'code' => $district->code,
                    'province' => [
                        'id' => $district->province->uuid,
                        'name' => $district->province->name,
                        'code' => $district->province->code,
                    ],
                ])
                ->all(),
        );
    }

    /**
     * Just enough to render a district picker.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function districtOptions(): array
    {
        return array_map(
            static fn (array $district): array => ['id' => $district['id'], 'name' => $district['name']],
            $this->districts(),
        );
    }

    /**
     * The sectors of one district, addressed by the district's public uuid.
     *
     * An unknown or empty uuid yields no sectors rather than an error: it is what a
     * form looks like before a district has been picked.
     *
     * @return array<int, SectorShape>
     */
    public function sectors(string $districtUuid): array
    {
        if ($districtUuid === '') {
            return [];
        }

        /** @var array<int, SectorShape> */
        return $this->cache->remember(
            self::SECTORS_KEY.'.'.$districtUuid,
            self::CACHE_TTL,
            static fn (): array => Sector::query()
                ->whereIn(
                    'district_id',
                    District::query()->where('uuid', $districtUuid)->select('id'),
                )
                ->orderBy('name')
                ->get()
                ->map(static fn (Sector $sector): array => [
                    'id' => $sector->uuid,
                    'name' => $sector->name,
                    'code' => $sector->code,
                ])
                ->all(),
        );
    }

    /**
     * Just enough to render a sector picker.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function sectorOptions(string $districtUuid): array
    {
        return array_map(
            static fn (array $sector): array => ['id' => $sector['id'], 'name' => $sector['name']],
            $this->sectors($districtUuid),
        );
    }

    /**
     * Drop the cached geography. Only a re-seed should ever need this.
     */
    public function flush(): void
    {
        // Read the districts before dropping them: their uuids are the only handle
        // on the per-district sector entries.
        $districts = $this->districts();

        foreach ($districts as $district) {
            $this->cache->forget(self::SECTORS_KEY.'.'.$district['id']);
        }

        $this->cache->forget(self::DISTRICTS_KEY);
    }
}
