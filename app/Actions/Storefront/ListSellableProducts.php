<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;

/**
 * The one query behind every customer-facing product list: the catalog, a category
 * page and a vendor's shop page all run through here.
 *
 * It always starts from Product::sellable(), which is the storefront visibility rule —
 * published AND owned by a vendor still eligible to sell. A vendor whose subscription
 * lapsed therefore vanishes from every list at once, without their data being touched.
 */
final readonly class ListSellableProducts
{
    public function __construct(private SearchSellableProducts $search) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     min_price?: int|null,
     *     max_price?: int|null,
     *     sort?: string|null,
     *     category_ids?: array<int, int>,
     *     vendor_id?: int|null,
     * }  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->sellable()
            ->with(['vendor', 'media']);

        $categoryIds = $filters['category_ids'] ?? [];

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        if (($filters['vendor_id'] ?? null) !== null) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (($filters['min_price'] ?? null) !== null) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (($filters['max_price'] ?? null) !== null) {
            $query->where('price', '<=', $filters['max_price']);
        }

        $this->search->handle($query, $filters['search'] ?? null);

        $this->applySort($query, $filters['sort'] ?? null);

        return $query
            ->paginate(Config::integer('marketplace.catalog.per_page'))
            ->withQueryString();
    }

    /**
     * Every sort is tie-broken on the primary key so paging can never show the same
     * product twice or skip one.
     *
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderBy('id'),
            default => $query->latest('published_at')->orderByDesc('id'),
        };
    }
}
