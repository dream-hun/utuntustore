<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use App\Concerns\ProductValidationRules;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Override;

/**
 * Validation shared by product create and edit.
 *
 * The shop is never submitted here: it is the vendor the `vendor` middleware already
 * resolved from the signed-in user, so no payload can aim a product at another shop.
 */
final class ProductRequest extends FormRequest
{
    use ProductValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->productRules(resolve(Vendor::class));
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
}
