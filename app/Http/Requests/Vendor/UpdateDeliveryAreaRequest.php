<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Editing the terms of an area a vendor already covers.
 *
 * The district and sector are not accepted here: an area cannot be moved, only added
 * or removed, which keeps the sector-belongs-to-district invariant true by
 * construction.
 */
final class UpdateDeliveryAreaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'estimated_days_min' => ['required', 'integer', 'min:0', 'max:60'],
            'estimated_days_max' => ['required', 'integer', 'min:0', 'max:60', 'gte:estimated_days_min'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'delivery_fee' => __('delivery fee'),
            'estimated_days_min' => __('shortest estimate'),
            'estimated_days_max' => __('longest estimate'),
        ];
    }
}
