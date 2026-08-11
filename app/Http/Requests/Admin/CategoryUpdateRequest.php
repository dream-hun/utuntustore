<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Validation\Validator;

/**
 * The category form plus the cycle guard.
 *
 * The tree is self-referencing and the key cascades on delete, so a category parented
 * to itself or to one of its own descendants would orphan a whole branch and make any
 * walk of the tree infinite.
 */
final class CategoryUpdateRequest extends CategoryRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('parent')) {
                return;
            }

            $category = $this->route('category');

            if (! $category instanceof Category) {
                return;
            }

            $parentId = $this->parentId();

            if ($parentId === null) {
                return;
            }

            if ($parentId === $category->id || in_array($parentId, $this->descendantIds($category), true)) {
                $validator->errors()->add(
                    'parent',
                    __('A category cannot sit under itself or under one of its own subcategories.'),
                );
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(Category $category): array
    {
        $ids = [];
        $frontier = [$category->id];

        while ($frontier !== []) {
            /** @var array<int, int> $children */
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            $frontier = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
