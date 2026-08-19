<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ProductValidationRules;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Override;

/**
 * An admin editing a product in a shop's catalog.
 *
 * The shop is deliberately absent, unlike on the create form: moving a product between
 * catalogs would strand it away from the delivery coverage it was bought under and from
 * the vendor orders that already reference it. The owning shop comes from the routed
 * product, which is also what scopes the SKU uniqueness check.
 */
final class ProductUpdateRequest extends FormRequest
{
    use ProductValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->productRules($this->product()->vendor);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return $this->productMessages();
    }

    /**
     * @return array{name: string, category_id: string, description: string|null, short_description: string|null, sku: string|null, price: int, compare_at_price: int|null, stock_quantity: int, low_stock_threshold: int, weight: int|null}
     */
    public function attributesForProduct(): array
    {
        return $this->productAttributes();
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function images(): array
    {
        return $this->productImages();
    }

    /**
     * The product being edited, as route model binding resolved it.
     */
    private function product(): Product
    {
        $product = $this->route('product');

        abort_unless($product instanceof Product, 404);

        return $product;
    }
}
