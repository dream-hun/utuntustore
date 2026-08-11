<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Support\Cast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared shape of the category form, which creating and editing submit identically.
 *
 * The slug is absent on purpose: it is derived from the name so the public URL cannot
 * drift away from what the category is called.
 */
abstract class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    final public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * The validated payload in the shape {@see \App\Actions\Admin\SaveCategory} expects.
     *
     * @return array{
     *     name: string,
     *     parent_id: int|null,
     *     description: string|null,
     *     is_active: bool,
     *     sort_order: int
     * }
     */
    final public function categoryData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'parent_id' => $this->parentId(),
            'description' => $this->filled('description')
                ? $this->string('description')->toString()
                : null,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    protected function parentId(): ?int
    {
        if (! $this->filled('parent')) {
            return null;
        }

        $parentId = Category::query()
            ->where('uuid', $this->string('parent')->toString())
            ->value('id');

        return $parentId === null ? null : Cast::int($parentId);
    }
}
