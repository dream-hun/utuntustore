<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeleteCategory;
use App\Actions\Admin\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryStoreRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalog taxonomy every vendor's products hang off.
 *
 * Categories are platform-owned reference data: vendors pick from this list and can
 * never extend it, which is what keeps the storefront's browse experience coherent
 * across thousands of independently run shops.
 */
final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = mb_trim($request->string('search')->toString());

        $categories = Category::query()
            ->with('parent:id,uuid,name')
            ->withCount(['products', 'children'])
            ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Category $category): array => [
                'id' => $category->uuid,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'products_count' => $category->products_count,
                'children_count' => $category->children_count,
                'parent' => $category->parent === null ? null : [
                    'id' => $category->parent->uuid,
                    'name' => $category->parent->name,
                ],
            ]);

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'filters' => ['search' => $search],

            // Every category is a candidate parent; the update request rejects the ones
            // that would create a cycle.
            'parents' => Category::query()
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn (Category $category): array => [
                    'id' => $category->uuid,
                    'name' => $category->name,
                ])
                ->all(),
        ]);
    }

    public function store(CategoryStoreRequest $request, SaveCategory $save): RedirectResponse
    {
        $category = $save->handle($request->categoryData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category ":name" created.', ['name' => $category->name]),
        ]);

        return back();
    }

    public function update(CategoryUpdateRequest $request, Category $category, SaveCategory $save): RedirectResponse
    {
        $save->handle($request->categoryData(), $category);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category ":name" updated.', ['name' => $category->name]),
        ]);

        return back();
    }

    /**
     * Deleting is blocked while the category is still in use.
     *
     * The action raises a validation error carrying the reason; it is turned into a
     * toast here because the delete happens in a confirmation dialog with no field to
     * hang an inline error on.
     */
    public function destroy(Category $category, DeleteCategory $delete): RedirectResponse
    {
        try {
            $delete->handle($category);
        } catch (ValidationException $validationException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $validationException->validator->errors()->first('category'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category ":name" deleted.', ['name' => $category->name]),
        ]);

        return back();
    }
}
