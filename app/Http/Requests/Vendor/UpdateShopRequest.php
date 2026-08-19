<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Override;

/**
 * Ownership is guaranteed by the `vendor` middleware, which resolves the current
 * user's own vendor — there is no code path here that could address another shop.
 */
final class UpdateShopRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'banner' => ['nullable', 'image', 'max:8192'],
            'remove_logo' => ['boolean'],
            'remove_banner' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'shop_name' => __('shop name'),
            'delivery_notes' => __('delivery notes'),
        ];
    }

    /**
     * @return array{shop_name: string, description: string|null, phone: string, email: string|null, delivery_notes: string|null}
     */
    public function profile(): array
    {
        return [
            'shop_name' => $this->string('shop_name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'phone' => $this->string('phone')->toString(),
            'email' => $this->filled('email') ? $this->string('email')->toString() : null,
            'delivery_notes' => $this->filled('delivery_notes') ? $this->string('delivery_notes')->toString() : null,
        ];
    }

    public function logo(): ?UploadedFile
    {
        $logo = $this->file('logo');

        return $logo instanceof UploadedFile ? $logo : null;
    }

    public function banner(): ?UploadedFile
    {
        $banner = $this->file('banner');

        return $banner instanceof UploadedFile ? $banner : null;
    }
}
