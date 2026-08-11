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
use Inertia\Inertia;
use Inertia\Response;

/**
 * The full catalog: search, filter by category, price range and shop, sorted by
 * newest or price.
 *
 * Vendor and category filters arrive as slugs so the URL a customer shares is
 * readable and survives a re-seed of the database.
 */
final class CatalogController extends Controller
{
    use PresentsCatalog;

    public function __invoke(
        CatalogFilterRequest $request,
        ListSellableProducts $products,
        ResolveCategoryBranch $branch,
    ): Response {
        $category = $request->filled('category')
            ? Category::query()->where('slug', $request->string('category'))->first()
            : null;

        // Only sellable vendors may be filtered on, so a lapsed shop cannot be
        // reached by hand-editing the query string either.
        $vendor = $request->filled('vendor')
            ? Vendor::query()->sellable()->where('slug', $request->string('vendor'))->first()
            : null;

        $paginator = $products->handle([
            ...$request->catalogFilters(),
            'category_ids' => $category === null ? [] : $branch->ids($category),
            'vendor_id' => $vendor?->id,
        ]);

        return Inertia::render('storefront/catalog', [
            'products' => $paginator->through(fn (Product $product): array => $this->productCard($product)),

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
