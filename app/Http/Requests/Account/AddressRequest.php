<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Enums\AddressType;
use App\Models\District;
use App\Models\Sector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a Rwandan delivery address.
 *
 * District and sector are required and must belong together, because vendor delivery
 * coverage is matched on exactly that pair. There is no postal code: Rwanda has none
 * in daily use, and the landmark carries that weight instead.
 */
final class AddressRequest extends FormRequest
{
    private ?Sector $resolvedSector = null;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AddressType::class)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'district' => ['required', 'string', Rule::exists(District::class, 'uuid')],
            'sector' => ['required', 'string', Rule::exists(Sector::class, 'uuid')],
            'cell' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * Reject a sector that is not inside the chosen district.
     *
     * The form scopes its sector list to the district, so a mismatch means a stale or
     * hand-crafted payload — and accepting it would produce an address no vendor's
     * coverage rules can match.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $sector = $this->sector();

                if ($sector->district->uuid !== $this->string('district')->toString()) {
                    $validator->errors()->add('sector', __('That sector is not in the chosen district.'));
                }
            },
        ];
    }

    /**
     * The attributes SaveAddress expects, with the district derived from the sector so
     * the stored pair is always internally consistent.
     *
     * @return array{type: string, first_name: string, last_name: string, phone: string, district_id: int, sector_id: int, cell: string|null, village: string|null, address_line: string|null, landmark: string|null, is_default: bool}
     */
    public function addressAttributes(): array
    {
        $sector = $this->sector();

        return [
            'type' => $this->string('type')->toString(),
            'first_name' => $this->string('first_name')->toString(),
            'last_name' => $this->string('last_name')->toString(),
            'phone' => $this->string('phone')->toString(),
            'district_id' => $sector->district_id,
            'sector_id' => $sector->id,
            'cell' => $this->filled('cell') ? $this->string('cell')->toString() : null,
            'village' => $this->filled('village') ? $this->string('village')->toString() : null,
            'address_line' => $this->filled('address_line') ? $this->string('address_line')->toString() : null,
            'landmark' => $this->filled('landmark') ? $this->string('landmark')->toString() : null,
            'is_default' => $this->boolean('is_default'),
        ];
    }

    private function sector(): Sector
    {
        return $this->resolvedSector ??= Sector::query()
            ->with('district')
            ->where('uuid', $this->string('sector')->toString())
            ->firstOrFail();
    }
}
