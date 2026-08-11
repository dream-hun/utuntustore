<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\VendorStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VendorModerateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(VendorStatus::class)->only([
                    VendorStatus::Approved,
                    VendorStatus::Rejected,
                    VendorStatus::Suspended,
                ]),
            ],
        ];
    }

    public function status(): VendorStatus
    {
        return VendorStatus::from($this->string('status')->toString());
    }
}
