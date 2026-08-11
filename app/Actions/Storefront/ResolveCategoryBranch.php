<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * The active subcategories of a category, in the order they should be shown.
 *
 * A customer picking "Electronics" means the whole branch, not just the products
 * that happened to be filed at the top level — so both the catalog's category filter
 * and the category page itself list a category together with its children.
 *
 * They used to work that out separately, and disagreed: the category page filtered
 * the children by is_active while the catalog filter did not, so deactivating a
 * subcategory hid it from one screen and not the other. Deactivating a category is
 * meant to be a merchandising tool, so the active filter is the correct behaviour
 * and this is now the only place that decides it.
 */
final readonly class ResolveCategoryBranch
{
    /**
     * @return Collection<int, Category>
     */
    public function handle(Category $category): Collection
    {
        return $category->children()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * The category and its active children, as the ids a product query filters on.
     *
     * @return array<int, int>
     */
    public function ids(Category $category): array
    {
        return [
            $category->id,
            ...$this->handle($category)
                ->map(static fn (Category $child): int => $child->id)
                ->all(),
        ];
    }
}
