<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Concerns\ResolvesAuthenticatedUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Submitting a checkout.
 *
 * Only the address and an optional coupon come from the customer — every price,
 * fee and eligibility decision is re-derived server side by BuildCheckoutQuote, so
 * nothing a client can post changes what the order costs.
 */
final class PlaceOrderRequest extends FormRequest
{
    use ResolvesAuthenticatedUser;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_id' => [
                'required',
                'string',
                Rule::exists('addresses', 'uuid')->where('user_id', $this->authenticatedUser()->id),
            ],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.required' => __('Choose the address this order should be delivered to.'),
            'address_id.exists' => __('Choose the address this order should be delivered to.'),
        ];
    }
}
