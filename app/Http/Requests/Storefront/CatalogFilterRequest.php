<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The catalog's query string. Everything here is optional; an invalid filter is a
 * validation error rather than a silently ignored parameter, so a bad link never
 * quietly shows the customer the wrong products.
 */
final class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'vendor' => ['nullable', 'string', 'exists:vendors,slug'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'price_asc', 'price_desc'])],
        ];
    }

    /**
     * The filters as ListSellableProducts wants them, with the slug-based ones left
     * for the controller to resolve.
     *
     * @return array{
     *     search: string|null,
     *     min_price: int|null,
     *     max_price: int|null,
     *     sort: string|null,
     * }
     */
    public function catalogFilters(): array
    {
        return [
            'search' => $this->string('search')->trim()->value() ?: null,
            'min_price' => $this->has('min_price') ? $this->integer('min_price') : null,
            'max_price' => $this->has('max_price') ? $this->integer('max_price') : null,
            'sort' => $this->filled('sort') ? $this->string('sort')->toString() : null,
        ];
    }
}
