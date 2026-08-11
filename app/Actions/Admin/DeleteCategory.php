<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * Removes a category, but only when nothing depends on it.
 *
 * `products.category_id` is `restrictOnDelete`, so a category still holding products
 * cannot be dropped at the database level either — this check exists so the admin gets
 * a sentence explaining what to do instead of a foreign key violation. Deactivating is
 * the right move for a category being retired: it hides the category from navigation
 * while leaving its products, and their order history, untouched.
 *
 * Children are blocked separately: the self-referencing key cascades, so deleting a
 * parent would silently take its subcategories with it.
 */
final readonly class DeleteCategory
{
    /**
     * @throws ValidationException when the category is still in use.
     */
    public function handle(Category $category): void
    {
        $products = $category->products()->count();

        if ($products > 0) {
            throw ValidationException::withMessages([
                'category' => __(
                    'This category still has :count product(s). Move them to another category, or deactivate this one instead of deleting it.',
                    ['count' => $products],
                ),
            ]);
        }

        $children = $category->children()->count();

        if ($children > 0) {
            throw ValidationException::withMessages([
                'category' => __(
                    'This category has :count subcategory(ies). Delete or re-parent them first.',
                    ['count' => $children],
                ),
            ]);
        }

        $category->delete();
    }
}
