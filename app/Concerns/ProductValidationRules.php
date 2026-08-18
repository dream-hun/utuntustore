<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * The product form, submitted identically by a vendor editing their own catalog and by
 * an admin adding stock on a shop's behalf.
 *
 * It lives in one place because the two screens must not drift: a rule relaxed on the
 * admin form would let an admin write a product the owning vendor could never save
 * again.
 *
 * Prices and stock are whole numbers: RWF has no minor unit, so accepting decimals
 * here would only invite a value to be stored one hundredth of its real size.
 */
trait ProductValidationRules
{
    /**
     * @param  Vendor|null  $vendor  The shop the product belongs to; null while the
     *                               submitted vendor is itself invalid.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productRules(?Vendor $vendor): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'string', Rule::exists('categories', 'uuid')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:20000'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', $this->uniqueSkuRule($vendor)],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'compare_at_price' => ['nullable', 'integer', 'min:0', 'max:1000000000', 'gt:price'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function productMessages(): array
    {
        return [
            'compare_at_price.gt' => __('The original price must be higher than the selling price.'),
        ];
    }

    /**
     * @return array{name: string, category_id: string, description: string|null, short_description: string|null, sku: string|null, price: int, compare_at_price: int|null, stock_quantity: int, low_stock_threshold: int, weight: int|null}
     */
    protected function productAttributes(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'category_id' => $this->string('category_id')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'short_description' => $this->filled('short_description') ? $this->string('short_description')->toString() : null,
            'sku' => $this->filled('sku') ? $this->string('sku')->toString() : null,
            'price' => $this->integer('price'),
            'compare_at_price' => $this->filled('compare_at_price') ? $this->integer('compare_at_price') : null,
            'stock_quantity' => $this->integer('stock_quantity'),
            'low_stock_threshold' => $this->integer('low_stock_threshold'),
            'weight' => $this->filled('weight') ? $this->integer('weight') : null,
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    protected function productImages(): array
    {
        /** @var array<int, UploadedFile> */
        return $this->file('images', []);
    }

    /**
     * SKUs only have to be unique inside the shop that uses them, which is also what
     * the products table's composite unique index enforces.
     *
     * A null vendor cannot match any row, so the SKU passes and the request fails on
     * the vendor field instead of on a rule the submitter cannot act on.
     */
    private function uniqueSkuRule(?Vendor $vendor): Unique
    {
        $rule = Rule::unique('products', 'sku')->where('vendor_id', $vendor?->id);

        $product = $this->route('product');

        if ($product instanceof Product) {
            $rule->ignore($product->id);
        }

        return $rule;
    }
}
