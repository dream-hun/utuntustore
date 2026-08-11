<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\Vendor;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * A rotating handful of shops for the home page.
 *
 * The obvious way to write this is `inRandomOrder()->limit(8)`, which asks MySQL to
 * assign a random number to every eligible vendor and sort all of them to pick eight.
 * That is a full scan plus a filesort on the busiest page of the site, and it gets
 * slower every time a shop signs up — exactly backwards.
 *
 * Instead the eligible ids are cached for a few minutes and the shuffle happens in
 * PHP, leaving one primary-key lookup for the eight rows actually shown. The list of
 * shops eligible to be featured changes when a vendor is approved or a subscription
 * lapses, neither of which needs to be reflected within the minute.
 */
final readonly class PickFeaturedVendors
{
    private const string CACHE_KEY = 'storefront.featured_vendor_ids';

    private const int CACHE_TTL = 300;

    public function __construct(private CacheRepository $cache) {}

    /**
     * @return Collection<int, Vendor>
     */
    public function handle(int $limit = 8): Collection
    {
        $ids = $this->eligibleIds();

        if ($ids === []) {
            return new Collection;
        }

        shuffle($ids);

        return Vendor::query()
            ->whereKey(array_slice($ids, 0, $limit))
            ->with('media')
            ->get();
    }

    /**
     * The ids of every shop currently allowed to be shown.
     *
     * @return array<int, int>
     */
    private function eligibleIds(): array
    {
        /** @var array<int, int> */
        return $this->cache->remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            static fn (): array => Vendor::query()
                ->sellable()
                ->pluck('id')
                ->all(),
        );
    }
}
