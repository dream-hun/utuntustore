<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * Adding to the cart is open to guests — a shopper should never be asked to
 * register before they have decided to buy.
 */
final class AddToCartRequest extends FormRequest
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
            'product' => ['required', 'string', 'exists:products,uuid'],
            'variant' => ['nullable', 'string', 'exists:product_variants,uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Config::integer('marketplace.catalog.max_cart_quantity')],
        ];
    }
}
