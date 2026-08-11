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
use Inertia\Inertia;
use Inertia\Response;

/**
 * A category listing, including everything filed under its child categories.
 *
 * An inactive category is hidden from the storefront entirely rather than shown
 * empty, which is what makes deactivating one a usable merchandising tool.
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

        $category->load(['parent']);

        $children = $branch->handle($category);

        $paginator = $products->handle([
            ...$request->catalogFilters(),
            'category_ids' => [$category->id, ...$children->map(fn (Category $child): int => $child->id)->all()],
        ]);

        return Inertia::render('storefront/category', [
            'category' => [
                ...$this->categoryLink($category),
                'description' => $category->description,
                'parent' => $category->parent === null ? null : $this->categoryLink($category->parent),
            ],

            'children' => $children
                ->map(fn (Category $child): array => $this->categoryLink($child))
                ->all(),

            'products' => $paginator->through(fn (Product $product): array => $this->productCard($product)),

            'filters' => [
                'search' => $request->input('search'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'sort' => $request->input('sort') ?? 'newest',
            ],
        ]);
    }
}
