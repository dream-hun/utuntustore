<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Concerns\ResolvesAuthenticatedUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * Submitting a checkout.
 *
 * Only the address and an optional coupon come from the customer — every price,
 * fee and eligibility decision is re-derived server side by BuildCheckoutQuote, so
 * nothing a client can post changes what the order costs.
 *
 * `expected_total` is the exception that proves the rule: it is the total the screen
 * displayed, posted back so the controller can refuse an order whose price moved
 * underneath the customer. It is only ever compared, never priced from — the worst a
 * forged value can do is reject an order, and it can never make one cheaper.
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
            'expected_total' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * What the checkout screen last told the customer this order would cost.
     */
    public function expectedTotal(): int
    {
        return $this->integer('expected_total');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'address_id.required' => __('Choose the address this order should be delivered to.'),
            'address_id.exists' => __('Choose the address this order should be delivered to.'),
            'expected_total.required' => __('Please review your order total and confirm again.'),
            'expected_total.integer' => __('Please review your order total and confirm again.'),
        ];
    }
}
