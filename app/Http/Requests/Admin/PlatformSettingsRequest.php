<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * The platform's runtime configuration.
 *
 * The fee is a whole number of francs. RWF has no minor unit in daily use, so there is
 * nothing after the decimal point to accept.
 */
final class PlatformSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_subscription_fee' => ['required', 'integer', 'min:0', 'max:100000000'],
            'vendor_subscription_currency' => ['required', 'string', 'size:3', 'alpha'],
            'vendor_subscription_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'vendor_subscription_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'vendor_subscription_fee.integer' => __('The subscription fee is a whole number of francs.'),
        ];
    }

    /**
     * @return array{
     *     vendor_subscription_fee: int,
     *     vendor_subscription_currency: string,
     *     vendor_subscription_days: int,
     *     vendor_subscription_grace_days: int
     * }
     */
    public function settings(): array
    {
        return [
            'vendor_subscription_fee' => $this->integer('vendor_subscription_fee'),
            'vendor_subscription_currency' => mb_strtoupper($this->string('vendor_subscription_currency')->toString()),
            'vendor_subscription_days' => $this->integer('vendor_subscription_days'),
            'vendor_subscription_grace_days' => $this->integer('vendor_subscription_grace_days'),
        ];
    }
}
