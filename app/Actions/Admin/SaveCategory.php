<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Creates or updates a catalog category.
 *
 * The slug is derived from the name rather than typed, because it is a public URL
 * segment shared by the storefront and must stay unique across the whole tree. Once a
 * category exists its slug is left alone: rewriting it would break every link and
 * bookmark already pointing at that listing.
 */
final readonly class SaveCategory
{
    /**
     * @param  array{
     *     name: string,
     *     parent_id?: int|null,
     *     description?: string|null,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function handle(array $data, ?Category $category = null): Category
    {
        $category ??= new Category;

        $category->name = $data['name'];
        $category->parent_id = $data['parent_id'] ?? null;
        $category->description = $data['description'] ?? null;
        $category->is_active = $data['is_active'] ?? true;
        $category->sort_order = $data['sort_order'] ?? 0;

        if (! $category->exists) {
            $category->slug = $this->uniqueSlug($data['name']);
        }

        $category->save();

        return $category;
    }

    /**
     * Suffix the slug until it clears the unique index.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
