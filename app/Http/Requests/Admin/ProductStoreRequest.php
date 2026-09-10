<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ProductValidationRules;
use App\Concerns\ResolvesAuthenticatedUser;
use App\Enums\VendorStatus;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Override;

/**
 * An admin adding a product on a shop's behalf.
 *
 * A blank shop uses the admin's own store, provisioned after validation if needed.
 * An explicitly selected shop is validated like any other input.
 * Only approved shops are offered — a pending or rejected application has no catalog to
 * add to.
 *
 * Everything else is {@see ProductValidationRules}, unchanged, so a product an admin
 * writes is one its owner can still save.
 */
final class ProductStoreRequest extends FormRequest
{
    use ProductValidationRules;
    use ResolvesAuthenticatedUser;

    private ?Vendor $vendor = null;

    private bool $vendorResolved = false;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor' => [
                'nullable',
                'uuid',
                Rule::exists('vendors', 'uuid')->where('status', VendorStatus::Approved->value),
            ],
            'publish' => ['nullable', 'boolean'],
            ...$this->productRules($this->resolveVendor()),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return $this->productMessages();
    }

    /**
     * Publishing is what puts inventory in front of a buyer, so it answers to the
     * shop's own selling eligibility rather than to the admin's role.
     *
     * The check is here rather than left to the action so the message lands on the
     * toggle that caused it, in words aimed at an operator rather than at the vendor.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vendor = $this->resolveVendor();

            if ($vendor instanceof Vendor && $vendor->status !== VendorStatus::Approved) {
                $validator->errors()->add('vendor', __('This shop is not approved.'));
            }

            if (! $this->boolean('publish')) {
                return;
            }

            if (! $vendor instanceof Vendor || $vendor->canSell()) {
                return;
            }

            $validator->errors()->add(
                'publish',
                __('This shop cannot sell right now, so the product can only be saved as a draft.'),
            );
        });
    }

    /**
     * The shop the product is being added to.
     */
    public function vendor(): ?Vendor
    {
        return $this->resolveVendor();
    }

    public function shouldPublish(): bool
    {
        return $this->boolean('publish');
    }

    /**
     * @return array{name: string, category_id: string, description: string|null, short_description: string|null, sku: string|null, price: int, compare_at_price: int|null, stock_quantity: int, low_stock_threshold: int, weight: int|null}
     */
    public function attributesForProduct(): array
    {
        return $this->productAttributes();
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function images(): array
    {
        return $this->productImages();
    }

    /**
     * Looked up once: the rules, the publish check and the controller all need it.
     */
    private function resolveVendor(): ?Vendor
    {
        if ($this->vendorResolved) {
            return $this->vendor;
        }

        $this->vendorResolved = true;

        if (! $this->filled('vendor')) {
            return $this->vendor = $this->authenticatedUser()->vendor()->first();
        }

        $this->vendor = Vendor::query()
            ->where('uuid', $this->string('vendor')->toString())
            ->first();

        return $this->vendor;
    }
}
