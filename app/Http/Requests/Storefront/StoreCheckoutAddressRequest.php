<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Models\District;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new delivery address added inline at checkout.
 *
 * District and sector are required and must be a real pair from the reference data:
 * a sector that does not belong to the district on the same row silently breaks
 * delivery coverage matching.
 */
final class StoreCheckoutAddressRequest extends FormRequest
{
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'district_id' => ['required', 'string', 'exists:districts,uuid'],
            'sector_id' => [
                'required',
                'string',
                Rule::exists('sectors', 'uuid')->where(function (Builder $query): void {
                    $query->whereIn(
                        'district_id',
                        District::query()->where('uuid', $this->string('district_id')->toString())->select('id'),
                    );
                }),
            ],
            'cell' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:100'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],

            // Carried through so an applied coupon survives adding an address.
            'coupon' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sector_id.exists' => __('Choose a sector inside the district you selected.'),
        ];
    }

    /**
     * The address in the shape {@see \App\Actions\Storefront\CreateDeliveryAddress} expects.
     *
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     district_id: string,
     *     sector_id: string,
     *     cell: string|null,
     *     village: string|null,
     *     address_line: string|null,
     *     landmark: string|null,
     *     is_default: bool,
     * }
     */
    public function deliveryAddress(): array
    {
        return [
            'first_name' => $this->string('first_name')->toString(),
            'last_name' => $this->string('last_name')->toString(),
            'phone' => $this->string('phone')->toString(),
            'district_id' => $this->string('district_id')->toString(),
            'sector_id' => $this->string('sector_id')->toString(),
            'cell' => $this->filled('cell') ? $this->string('cell')->toString() : null,
            'village' => $this->filled('village') ? $this->string('village')->toString() : null,
            'address_line' => $this->filled('address_line') ? $this->string('address_line')->toString() : null,
            'landmark' => $this->filled('landmark') ? $this->string('landmark')->toString() : null,
            'is_default' => $this->boolean('is_default'),
        ];
    }

    /**
     * The coupon carried through the address form so applying one survives the detour.
     */
    public function couponCode(): ?string
    {
        return $this->filled('coupon') ? $this->string('coupon')->toString() : null;
    }
}
