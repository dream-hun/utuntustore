<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use App\Support\Cast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Declaring new delivery coverage.
 *
 * An empty `sector_ids` means "the whole district", which is the one-action case the
 * screen is built around. The two rules that actually protect coverage matching —
 * a sector must belong to its district, and an area may not be declared twice — live
 * in AddDeliveryCoverage, so they hold for every caller, not just this form.
 */
final class DeliveryCoverageRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'district_id' => ['required', 'string', Rule::exists('districts', 'uuid')],
            'sector_ids' => ['array', 'max:60'],
            'sector_ids.*' => ['string', Rule::exists('sectors', 'uuid')],
            'delivery_fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'estimated_days_min' => ['required', 'integer', 'min:0', 'max:60'],
            'estimated_days_max' => ['required', 'integer', 'min:0', 'max:60', 'gte:estimated_days_min'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'district_id' => __('district'),
            'sector_ids' => __('sectors'),
            'delivery_fee' => __('delivery fee'),
            'estimated_days_min' => __('shortest estimate'),
            'estimated_days_max' => __('longest estimate'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sectorUuids(): array
    {
        return $this->collect('sector_ids')
            ->map(static fn (mixed $uuid): string => Cast::string($uuid))
            ->values()
            ->all();
    }
}
