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
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A category listing, including everything filed under its child categories.
 *
 * An inactive category is hidden from the storefront entirely rather than shown
 * empty, which is what makes deactivating one a usable merchandising tool.
 *
 * The props are closures so a partial reload that never asks for them — the cart
 * drawer fetching `cartPreview`, for one — does not pay for this page's listing.
 */
final class CategoryController extends Controller
{
    use PresentsCatalog;

    public function __invoke(
        CatalogFilterRequest $request,
        Category $category,
        ListSellableProducts $products,
        ResolveCategoryBranch $branch,
    ): Response {
        abort_unless($category->is_active, 404);

        // One cheap indexed query, and both the `children` and `products` props need
        // it, so it is resolved up front rather than memoised. Nothing is cached on
        // `$this`: Laravel keeps the controller instance on the Route object, so a
        // property set here outlives the request and leaks into the next one.
        $children = $branch->handle($category);

        return Inertia::render('storefront/category', [
            'navCategories' => Inertia::defer(fn (): array => $this->navCategories()),

            'category' => function () use ($category): array {
                $category->load(['parent']);

                return [
                    ...$this->categoryLink($category),
                    'description' => $category->description,
                    'parent' => $category->parent === null ? null : $this->categoryLink($category->parent),
                ];
            },

            'children' => $children
                ->map(fn (Category $child): array => $this->categoryLink($child))
                ->all(),

            'products' => fn (): LengthAwarePaginator => $products->handle([
                ...$request->catalogFilters(),
                'category_ids' => [$category->id, ...$children->map(fn (Category $child): int => $child->id)->all()],
            ])->through(fn (Product $product): array => $this->productCard($product)),

            'filters' => [
                'search' => $request->input('search'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'sort' => $request->input('sort') ?? 'newest',
            ],
        ]);
    }
}
