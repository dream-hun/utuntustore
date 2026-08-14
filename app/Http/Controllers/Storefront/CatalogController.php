<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Storefront\ListSellableProducts;
use App\Actions\Storefront\ResolveCategoryBranch;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\PresentsCatalog;
use App\Http\Requests\Storefront\CatalogFilterRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The full catalog: search, filter by category, price range and shop, sorted by
 * newest or price.
 *
 * Vendor and category filters arrive as slugs so the URL a customer shares is
 * readable and survives a re-seed of the database.
 *
 * Every prop here is a closure rather than an already-computed value. A partial
 * reload — the cart drawer asking for `cartPreview`, say — still runs this whole
 * action, and Inertia can only skip a prop it has not already been handed the answer
 * to. Computing the paginator eagerly meant opening the drawer on this page ran the
 * catalog's COUNT, its SELECT and both eager loads, then threw all four away. Behind
 * closures they simply never run.
 */
final class CatalogController extends Controller
{
    use PresentsCatalog;

    public function __invoke(
        CatalogFilterRequest $request,
        ListSellableProducts $products,
        ResolveCategoryBranch $branch,
    ): Response {
        // Two indexed single-row lookups, and none at all unless a filter is actually
        // applied — cheap enough to resolve up front, which keeps them out of both
        // closures below without either having to memoise anything. Nothing is cached
        // on `$this`: Laravel keeps the controller instance on the Route object, so a
        // property set here outlives the request and leaks into the next one.
        $category = $request->filled('category')
            ? Category::query()->where('slug', $request->string('category'))->first()
            : null;

        // Only sellable vendors may be filtered on, so a lapsed shop cannot be
        // reached by hand-editing the query string either.
        $vendor = $request->filled('vendor')
            ? Vendor::query()->sellable()->where('slug', $request->string('vendor'))->first()
            : null;

        return Inertia::render('storefront/catalog', [
            'products' => fn (): LengthAwarePaginator => $products->handle([
                ...$request->catalogFilters(),
                'category_ids' => $category === null ? [] : $branch->ids($category),
                'vendor_id' => $vendor?->id,
            ])->through(fn (Product $product): array => $this->productCard($product)),

            'filters' => [
                'search' => $request->input('search'),
                'category' => $category?->slug,
                'vendor' => $vendor?->slug,
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'sort' => $request->input('sort') ?? 'newest',
            ],

            'categories' => Inertia::defer(fn (): array => Category::query()
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => $this->categoryLink($category))
                ->all()),

            'vendors' => Inertia::defer(fn (): array => Vendor::query()
                ->sellable()
                ->orderBy('shop_name')
                ->limit(100)
                ->get()
                ->map(fn (Vendor $vendor): array => $this->vendorRef($vendor))
                ->all()),
        ]);
    }
}
